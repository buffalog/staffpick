"use server";

import { revalidatePath } from "next/cache";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { prismaBase } from "@/lib/prisma";
import { sendEmail } from "@/lib/email";

/**
 * Phase 11(c) — Tenant Staff sends a Draft invoice to the Source.
 * Flips status Draft → Sent, fires the email via Resend (or stubs to
 * console in dev), and writes a delivery record to NotificationLog.
 */
export async function sendInvoice(invoiceId: string): Promise<void> {
  await withSession(async (ctx) => {
    const inv = await prisma.invoice.findFirst({
      where: { id: invoiceId },
      include: { source: { include: { contacts: true } }, request: { include: { subject: true } } },
    });
    if (!inv) throw new Error(`Invoice ${invoiceId} not found`);
    if (inv.status !== "Draft") {
      throw new Error(`Cannot send invoice in status ${inv.status}`);
    }

    const recipient =
      inv.source.contacts.find((c) => c.is_primary && c.email)?.email ??
      inv.source.contacts.find((c) => c.email)?.email ??
      inv.source.email ??
      null;
    if (!recipient) throw new Error("No source contact email on file");

    const baseUrl = process.env.NEXTAUTH_URL ?? "http://localhost:3000";
    const subjName = inv.request.subject
      ? `${inv.request.subject.given_name} ${inv.request.subject.family_name}`
      : "patient";
    const total = `$${(inv.total_cents / 100).toFixed(2)}`;
    const subjectLine = `Invoice ${inv.invoice_number} — ${total}`;
    const body =
      `Invoice ${inv.invoice_number} is ready for review.\n\n` +
      `Patient: ${subjName}\n` +
      `Total: ${total}\n` +
      `Due: ${inv.due_at?.toLocaleDateString() ?? "—"}\n\n` +
      `Review and mark paid here: ${baseUrl}/invoices/${inv.external_link}`;

    const log = await prisma.notificationLog.create({
      data: {
        tenant_id: ctx.tenantId,
        channel: "Email",
        status: "Queued",
        recipient,
        subject_line: subjectLine,
        body,
        entity_type: "Invoice",
        entity_id: inv.id,
        metadata: JSON.stringify({ phase: "11c", recipientType: "source" }),
      },
    });
    const result = await sendEmail({
      to: recipient,
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
          phase: "11c",
          recipientType: "source",
          channel: result.channel,
          externalId: result.externalId,
        }),
      },
    });

    await prisma.invoice.update({
      where: { id: inv.id },
      data: { status: "Sent", issued_at: new Date() },
    });

    // Phase 11 → 12 (Invoice Review & Payment — the Source's court now)
    await prisma.intakeRequest.update({
      where: { id: inv.request_id },
      data: { phase: "Phase12_InvoicePayment" },
    });
  });
  revalidatePath(`/dashboard/cases`);
}

/**
 * Phase 12 — Source marks an Invoice paid via the magic-link page.
 * No session required; the external_link slug is the auth factor.
 * Bypasses tenant-scope via prismaBase.
 */
export async function markInvoicePaid(externalLink: string): Promise<{
  invoiceId: string;
  requestId: string;
}> {
  const inv = await prismaBase.invoice.findFirst({
    where: { external_link: externalLink },
  });
  if (!inv) throw new Error("Invoice not found");
  if (inv.status === "Paid") {
    return { invoiceId: inv.id, requestId: inv.request_id };
  }
  if (inv.status !== "Sent") {
    throw new Error(`Cannot mark paid from status ${inv.status}`);
  }

  await prismaBase.invoice.update({
    where: { id: inv.id },
    data: { status: "Paid", paid_at: new Date() },
  });
  // Advance Phase 12 → Phase 13
  await prismaBase.intakeRequest.update({
    where: { id: inv.request_id },
    data: { phase: "Phase13_ProviderPayment" },
  });
  await prismaBase.userActivityLog.create({
    data: {
      tenant_id: inv.tenant_id,
      action: "Update",
      entity_type: "Invoice",
      entity_id: inv.id,
      metadata: JSON.stringify({
        event: "MarkedPaid",
        via: "source-magic-link",
      }),
    },
  });

  return { invoiceId: inv.id, requestId: inv.request_id };
}
