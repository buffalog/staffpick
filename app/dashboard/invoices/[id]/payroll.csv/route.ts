import { NextResponse } from "next/server";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { prismaBase } from "@/lib/prisma";
import type { CasePhase } from "@/lib/enums";

export const dynamic = "force-dynamic";

function csvEscape(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

export async function GET(
  _req: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;

  try {
    const result = await withSession(async (ctx) => {
      const invoice = await prisma.invoice.findFirst({
        where: { id },
        include: {
          request: { include: { subject: true } },
        },
      });
      if (!invoice) return null;
      const services = await prisma.service.findMany({
        where: { billed_invoice_id: invoice.id, billable: true },
        include: { provider: true },
        orderBy: [{ provider_id: "asc" }, { visit_date: "asc" }],
      });
      const rates = await prisma.tenantServiceRate.findMany({});
      const rateByCode = new Map(rates.map((r) => [r.service_code, r]));

      // Phase 14 auto-close — once the payroll CSV has been pulled at
      // least once after status=Paid, advance Phase 13 → Phase 14.
      const req = invoice.request;
      const phase = req.phase as CasePhase;
      if (
        invoice.status === "Paid" &&
        phase === "Phase13_ProviderPayment"
      ) {
        await prisma.intakeRequest.update({
          where: { id: req.id },
          data: {
            phase: "Phase14_Closed",
            status: "Closed",
            closed_at: new Date(),
          },
        });
        await prismaBase.userActivityLog.create({
          data: {
            tenant_id: ctx.tenantId,
            user_id: ctx.userId,
            action: "Update",
            entity_type: "IntakeRequest",
            entity_id: req.id,
            metadata: JSON.stringify({
              event: "PayrollCsvExported",
              invoice: invoice.invoice_number,
            }),
          },
        });
      }

      return { invoice, services, rateByCode };
    });

    if (!result) {
      return NextResponse.json({ error: "Not found" }, { status: 404 });
    }
    const { invoice, services, rateByCode } = result;

    const subjName = invoice.request.subject
      ? `${invoice.request.subject.given_name} ${invoice.request.subject.family_name}`
      : "—";

    const lines: string[] = [];
    lines.push(
      [
        "invoice_number",
        "case_id",
        "patient",
        "provider_email",
        "provider_name",
        "specialty",
        "visit_date",
        "service_code",
        "duration_minutes",
        "billable_amount_usd",
        "classification",
      ]
        .map(csvEscape)
        .join(","),
    );
    for (const s of services) {
      const rate = s.service_code ? rateByCode.get(s.service_code) : null;
      const amount = rate ? (rate.rate_cents / 100).toFixed(2) : "0.00";
      lines.push(
        [
          invoice.invoice_number,
          invoice.request_id,
          subjName,
          s.provider.email ?? "",
          `${s.provider.given_name} ${s.provider.family_name}`,
          s.provider.specialty ?? "",
          s.visit_date.toISOString().slice(0, 10),
          s.service_code ?? "",
          String(s.duration_minutes ?? ""),
          amount,
          s.provider.classification ?? "",
        ]
          .map((v) => csvEscape(String(v)))
          .join(","),
      );
    }
    const csv = lines.join("\n") + "\n";

    return new NextResponse(csv, {
      status: 200,
      headers: {
        "Content-Type": "text/csv; charset=utf-8",
        "Content-Disposition": `attachment; filename="payroll-${invoice.invoice_number}.csv"`,
      },
    });
  } catch (err) {
    console.error("[payroll.csv]", err);
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }
}
