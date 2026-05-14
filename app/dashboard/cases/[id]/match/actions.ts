"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { assertPhaseTransition } from "@/lib/case-state-machine";
import { sendEmail } from "@/lib/email";
import type { CasePhase } from "@/lib/enums";

/**
 * Tenant Staff approves a candidate Provider for an IntakeRequest.
 *
 * Side effects (in order):
 *   1. IntakeRequestProvider row created with approved=true.
 *   2. Phase walked through Phase4_MatchReview → Phase5_MatchNotification
 *      (the "match review" step is implicit in the staff clicking Approve).
 *   3. NotificationLog rows queued + sent to Source primary contact and
 *      Subject email (if present). Sends real emails via Resend if
 *      RESEND_API_KEY is set; otherwise logs to console.
 *
 * Throws on illegal phase transitions; the form will render the action
 * panel based on the state machine so the user shouldn't be able to
 * trigger an illegal advance.
 */
export async function approveMatch(
  requestId: string,
  providerId: string,
): Promise<void> {
  await withSession(async (ctx) => {
    const req = await prisma.intakeRequest.findFirst({
      where: { id: requestId },
      include: {
        subject: true,
        source: { include: { contacts: true } },
      },
    });
    if (!req) throw new Error(`IntakeRequest ${requestId} not found`);

    const provider = await prisma.provider.findFirst({
      where: { id: providerId },
    });
    if (!provider) throw new Error(`Provider ${providerId} not found`);

    // 1. Create assignment
    await prisma.intakeRequestProvider.create({
      data: {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        provider_id: providerId,
        approved: true,
        approved_at: new Date(),
        approved_by: ctx.userId,
      },
    });

    // 2. Walk phases Phase3 → Phase4 → Phase5
    const currentPhase = req.phase as CasePhase;
    if (currentPhase === "Phase3_MatchingKickoff") {
      assertPhaseTransition(currentPhase, "Phase4_MatchReview");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase4_MatchReview" },
      });
      assertPhaseTransition("Phase4_MatchReview", "Phase5_MatchNotification");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase5_MatchNotification" },
      });
    } else if (currentPhase === "Phase4_MatchReview") {
      assertPhaseTransition(currentPhase, "Phase5_MatchNotification");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase5_MatchNotification" },
      });
    } else {
      throw new Error(
        `Cannot approve a match from phase ${currentPhase} — current legal next phases differ.`,
      );
    }

    // 3. Queue + send notifications (Source primary contact + Subject)
    const recipients: Array<{ email: string; recipientType: string }> = [];
    const primaryContact = req.source?.contacts.find((c) => c.is_primary && c.email)
      ?? req.source?.contacts.find((c) => c.email);
    if (primaryContact?.email) {
      recipients.push({ email: primaryContact.email, recipientType: "source" });
    } else if (req.source?.email) {
      recipients.push({ email: req.source.email, recipientType: "source" });
    }
    if (req.subject?.email) {
      recipients.push({ email: req.subject.email, recipientType: "subject" });
    }

    const subjectName = req.subject
      ? `${req.subject.given_name} ${req.subject.family_name}`
      : "your patient";
    const providerName = `${provider.given_name} ${provider.family_name}`;
    const subjectLine = `Provider matched for ${subjectName}`;
    const body =
      `${providerName} (${provider.specialty ?? "clinician"}) has been ` +
      `assigned to the referral for ${subjectName}.\n\n` +
      `Our care team will be in touch shortly to coordinate the first visit.`;

    for (const r of recipients) {
      const log = await prisma.notificationLog.create({
        data: {
          tenant_id: ctx.tenantId,
          channel: "Email",
          status: "Queued",
          recipient: r.email,
          subject_line: subjectLine,
          body,
          entity_type: "IntakeRequest",
          entity_id: requestId,
          metadata: JSON.stringify({ recipientType: r.recipientType }),
        },
      });
      const result = await sendEmail({
        to: r.email,
        subject: subjectLine,
        text: body,
      });
      await prisma.notificationLog.update({
        where: { id: log.id },
        data: {
          status: result.delivered ? "Sent" : "Failed",
          sent_at: result.delivered ? new Date() : null,
          failed_at: result.delivered ? null : new Date(),
          metadata: JSON.stringify({
            recipientType: r.recipientType,
            channel: result.channel,
            externalId: result.externalId,
          }),
        },
      });
    }
  });

  revalidatePath(`/dashboard/cases/${requestId}`);
  revalidatePath(`/dashboard/cases/${requestId}/match`);
  revalidatePath("/dashboard/inbox");
  redirect(`/dashboard/cases/${requestId}`);
}
