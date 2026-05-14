import { randomUUID } from "node:crypto";
import { prismaBase } from "@/lib/prisma";

/**
 * Phase 11(a) — automated invoice generation.
 *
 * Aggregates all billable `Service` rows on the request (across all
 * Resolution Plans), matches each visit to a `TenantServiceRate` by
 * service_code, and creates one Invoice row in `Draft` status. Service
 * rows are linked to the Invoice via `billed_invoice_id` so they can't
 * be double-billed.
 *
 * MVP: single aggregate invoice per case (all approved Providers' work
 * combined). Per-Provider invoicing is post-MVP.
 *
 * Uses `prismaBase` to bypass the tenant-scope extension — the caller
 * passes tenantId explicitly so this works inside or outside of
 * `withTenantContext`.
 */
export async function generateInvoice(
  requestId: string,
  tenantId: string,
): Promise<{ invoiceId: string; total_cents: number }> {
  const services = await prismaBase.service.findMany({
    where: {
      tenant_id: tenantId,
      request_id: requestId,
      billable: true,
      billed_invoice_id: null,
    },
  });

  if (services.length === 0) {
    throw new Error(
      `No billable, un-billed Services for IntakeRequest ${requestId}`,
    );
  }

  const request = await prismaBase.intakeRequest.findFirst({
    where: { tenant_id: tenantId, id: requestId },
    include: { source: true, resolution_plans: true },
  });
  if (!request) throw new Error(`IntakeRequest ${requestId} not found`);
  if (!request.source_id) throw new Error("Cannot invoice without a Source");

  const rates = await prismaBase.tenantServiceRate.findMany({
    where: { tenant_id: tenantId },
  });
  const rateByCode = new Map(rates.map((r) => [r.service_code, r]));

  let subtotal_cents = 0;
  for (const s of services) {
    const code = s.service_code;
    if (!code) continue;
    const rate = rateByCode.get(code);
    if (!rate) continue;
    subtotal_cents += rate.rate_cents;
  }
  if (subtotal_cents === 0) {
    throw new Error(
      "Computed $0 invoice — check that visits have service_codes that match TenantServiceRate entries.",
    );
  }

  // Invoice numbering: INV-YYYY-NNNN, per-tenant year sequence.
  const year = new Date().getFullYear();
  const yearStart = new Date(year, 0, 1);
  const nextYearStart = new Date(year + 1, 0, 1);
  const existingThisYear = await prismaBase.invoice.count({
    where: {
      tenant_id: tenantId,
      created_at: { gte: yearStart, lt: nextYearStart },
    },
  });
  const seq = String(existingThisYear + 1).padStart(4, "0");
  const invoice_number = `INV-${year}-${seq}`;

  // External link slug for the magic-link Source view
  const external_link = randomUUID().replace(/-/g, "").slice(0, 24);

  // Pick the active plan id (most recent) for the FK; safe to leave null too.
  const planId =
    request.resolution_plans.sort(
      (a, b) => b.start_date.getTime() - a.start_date.getTime(),
    )[0]?.id ?? null;

  const due = new Date();
  due.setDate(due.getDate() + 30);

  const invoice = await prismaBase.invoice.create({
    data: {
      tenant_id: tenantId,
      request_id: requestId,
      plan_id: planId,
      source_id: request.source_id,
      invoice_number,
      status: "Draft",
      subtotal_cents,
      total_cents: subtotal_cents, // no tax/discount for MVP
      currency: "USD",
      issued_at: new Date(),
      due_at: due,
      external_link,
    },
  });

  // Link services to the invoice so they can't be double-billed
  await prismaBase.service.updateMany({
    where: { id: { in: services.map((s) => s.id) }, tenant_id: tenantId },
    data: { billed_invoice_id: invoice.id },
  });

  // Phase 11(b) — queue a NotificationLog draft for staff review
  await prismaBase.notificationLog.create({
    data: {
      tenant_id: tenantId,
      channel: "Email",
      status: "Queued",
      recipient: request.source?.email ?? "",
      subject_line: `Invoice ${invoice_number} ready for review`,
      body: `Invoice ${invoice_number} for $${(subtotal_cents / 100).toFixed(
        2,
      )} is in Draft. Open the case to review and send to the source.`,
      entity_type: "Invoice",
      entity_id: invoice.id,
      metadata: JSON.stringify({ phase: "11b", recipientType: "staff-review" }),
    },
  });

  return { invoiceId: invoice.id, total_cents: subtotal_cents };
}
