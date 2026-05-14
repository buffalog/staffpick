import { NextResponse } from "next/server";
import { renderToBuffer } from "@react-pdf/renderer";
import { createElement } from "react";
import { prismaBase } from "@/lib/prisma";
import {
  InvoicePdfDocument,
  type InvoicePdfData,
} from "@/lib/invoicing/invoice-pdf";

export const dynamic = "force-dynamic";

export async function GET(
  _req: Request,
  { params }: { params: Promise<{ link: string }> },
) {
  const { link } = await params;

  const invoice = await prismaBase.invoice.findFirst({
    where: { external_link: link },
    include: {
      tenant: true,
      source: { include: { contacts: { where: { is_primary: true } } } },
      request: {
        include: {
          subject: true,
          services: {
            where: { billable: true },
            include: { provider: true },
            orderBy: { visit_date: "asc" },
          },
        },
      },
    },
  });
  if (!invoice) {
    return NextResponse.json({ error: "Not found" }, { status: 404 });
  }

  const rates = await prismaBase.tenantServiceRate.findMany({
    where: { tenant_id: invoice.tenant_id },
  });
  const rateByCode = new Map(rates.map((r) => [r.service_code, r]));

  const subj = invoice.request.subject;
  const contact = invoice.source.contacts[0] ?? null;

  const data: InvoicePdfData = {
    tenantName: invoice.tenant.name,
    invoice: {
      number: invoice.invoice_number,
      status: invoice.status,
      currency: invoice.currency,
      total_cents: invoice.total_cents,
      issued_at: invoice.issued_at,
      due_at: invoice.due_at,
      paid_at: invoice.paid_at,
    },
    source: {
      name: invoice.source.name,
      email: invoice.source.email,
      contactName: contact
        ? `${contact.given_name ?? ""} ${contact.family_name ?? ""}`.trim()
        : null,
    },
    subject: {
      name: subj ? `${subj.given_name} ${subj.family_name}` : "—",
    },
    lines: invoice.request.services
      .filter((s) => s.billed_invoice_id === invoice.id)
      .map((s) => {
        const rate = s.service_code ? rateByCode.get(s.service_code) : null;
        return {
          visitDate: s.visit_date,
          serviceCode: s.service_code,
          description: rate?.description ?? "",
          provider: `${s.provider.given_name} ${s.provider.family_name}`,
          durationMinutes: s.duration_minutes,
          amountCents: rate?.rate_cents ?? 0,
        };
      }),
  };

  // @react-pdf/renderer's types disagree with React 19 on Document props
  // shape; the runtime is correct — the cast is intentional.
  const element = createElement(InvoicePdfDocument, data) as unknown as Parameters<
    typeof renderToBuffer
  >[0];
  const buffer = await renderToBuffer(element);

  return new NextResponse(buffer as unknown as BodyInit, {
    status: 200,
    headers: {
      "Content-Type": "application/pdf",
      "Content-Disposition": `inline; filename="${invoice.invoice_number}.pdf"`,
    },
  });
}
