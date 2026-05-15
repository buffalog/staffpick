"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { prismaBase } from "@/lib/prisma";

/**
 * Phase 12 — Source marks an Invoice paid via the public magic-link page.
 * No session: the `external_link` slug IS the auth factor, so this scopes by
 * it explicitly and uses `prismaBase` (no tenant context to derive). Lives
 * with the public /invoices/[link] route, not under /dashboard.
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

export async function markPaidViaLink(externalLink: string): Promise<void> {
  await markInvoicePaid(externalLink);
  revalidatePath(`/invoices/${externalLink}`);
  redirect(`/invoices/${externalLink}?paid=1`);
}
