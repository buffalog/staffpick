"use server";

import { revalidatePath } from "next/cache";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { assertPhaseTransition } from "@/lib/case-state-machine";
import type { CasePhase } from "@/lib/enums";

export async function postCaseMessage(
  requestId: string,
  formData: FormData,
): Promise<void> {
  const body = String(formData.get("body") ?? "").trim();
  if (!body || body.length > 4000) return;

  await withSession(async (ctx) => {
    await prisma.caseMessage.create({
      data: {
        tenant_id: ctx.tenantId,
        request_id: requestId,
        sender_user_id: ctx.userId,
        body,
      },
    });

    // First message at Phase 5 (Match Notification sent) triggers Phase 6
    // (Collaboration) — staff initiating contact with the assigned Provider.
    const req = await prisma.intakeRequest.findFirst({
      where: { id: requestId },
    });
    if (req && (req.phase as CasePhase) === "Phase5_MatchNotification") {
      assertPhaseTransition("Phase5_MatchNotification", "Phase6_Collaboration");
      await prisma.intakeRequest.update({
        where: { id: requestId },
        data: { phase: "Phase6_Collaboration" },
      });
    }
  });

  revalidatePath(`/dashboard/cases/${requestId}`);
}
