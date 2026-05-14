import { notFound } from "next/navigation";
import { prismaBase } from "@/lib/prisma";
import { markPaidViaLink } from "./actions";

export const dynamic = "force-dynamic";

type SearchParams = Promise<{ paid?: string }>;

export default async function SourceInvoicePage({
  params,
  searchParams,
}: {
  params: Promise<{ link: string }>;
  searchParams: SearchParams;
}) {
  const { link } = await params;
  const { paid } = await searchParams;

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
  if (!invoice) notFound();

  const rates = await prismaBase.tenantServiceRate.findMany({
    where: { tenant_id: invoice.tenant_id },
  });
  const rateByCode = new Map(rates.map((r) => [r.service_code, r]));

  const subjName = invoice.request.subject
    ? `${invoice.request.subject.given_name} ${invoice.request.subject.family_name}`
    : "—";
  const sourceContact = invoice.source.contacts[0];

  return (
    <div className="min-h-screen bg-background text-foreground py-10">
      <div className="max-w-3xl mx-auto px-4 space-y-6">
        {paid === "1" && (
          <div className="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-300">
            Thank you — payment recorded.
          </div>
        )}

        <header className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-3xl font-semibold tracking-tight">
              {invoice.tenant.name}
            </h1>
            <p className="text-xs text-muted-foreground">Invoice</p>
          </div>
          <div className="text-right">
            <p className="font-mono text-sm">{invoice.invoice_number}</p>
            <p className="text-xs text-muted-foreground">
              {invoice.status} ·{" "}
              {invoice.issued_at?.toLocaleDateString() ?? "—"}
            </p>
            {invoice.due_at && (
              <p className="text-xs text-muted-foreground">
                Due {invoice.due_at.toLocaleDateString()}
              </p>
            )}
          </div>
        </header>

        <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="rounded-md border bg-card p-3">
            <div className="text-xs uppercase tracking-wide text-muted-foreground mb-1">
              Bill to
            </div>
            <div className="text-sm">
              <div className="font-medium">{invoice.source.name}</div>
              {sourceContact && (
                <div>
                  {sourceContact.given_name} {sourceContact.family_name}
                </div>
              )}
              <div className="text-xs text-muted-foreground">
                {invoice.source.email ?? "—"}
              </div>
            </div>
          </div>
          <div className="rounded-md border bg-card p-3">
            <div className="text-xs uppercase tracking-wide text-muted-foreground mb-1">
              For patient
            </div>
            <div className="text-sm">{subjName}</div>
          </div>
        </section>

        <section className="rounded-md border bg-card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="px-3 py-2 font-medium">Date</th>
                <th className="px-3 py-2 font-medium">Service</th>
                <th className="px-3 py-2 font-medium">Provider</th>
                <th className="px-3 py-2 font-medium text-right">Min</th>
                <th className="px-3 py-2 font-medium text-right">Amount</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {invoice.request.services
                .filter((s) => s.billed_invoice_id === invoice.id)
                .map((s) => {
                  const rate = s.service_code ? rateByCode.get(s.service_code) : null;
                  const amount = rate?.rate_cents ?? 0;
                  return (
                    <tr key={s.id}>
                      <td className="px-3 py-2 tabular-nums">
                        {s.visit_date.toLocaleDateString()}
                      </td>
                      <td className="px-3 py-2">
                        <div className="font-mono">{s.service_code ?? "—"}</div>
                        <div className="text-xs text-muted-foreground">
                          {rate?.description ?? ""}
                        </div>
                      </td>
                      <td className="px-3 py-2">
                        {s.provider.given_name} {s.provider.family_name}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums">
                        {s.duration_minutes ?? "—"}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums">
                        ${(amount / 100).toFixed(2)}
                      </td>
                    </tr>
                  );
                })}
            </tbody>
            <tfoot className="bg-muted/30">
              <tr>
                <td className="px-3 py-2 font-medium" colSpan={4}>
                  Total
                </td>
                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                  ${(invoice.total_cents / 100).toFixed(2)} {invoice.currency}
                </td>
              </tr>
            </tfoot>
          </table>
        </section>

        <section className="flex items-center justify-between gap-3">
          <a
            href={`/invoices/${link}/pdf`}
            className="text-sm underline"
          >
            Download PDF
          </a>
          {invoice.status === "Paid" ? (
            <span className="rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 px-3 py-1.5 text-sm">
              Paid {invoice.paid_at?.toLocaleDateString() ?? ""}
            </span>
          ) : invoice.status === "Sent" ? (
            <form action={markPaidViaLink.bind(null, link)}>
              <button
                type="submit"
                className="rounded-md bg-primary text-primary-foreground px-4 py-2 text-sm font-medium hover:opacity-90"
              >
                Mark paid
              </button>
            </form>
          ) : (
            <span className="text-xs text-muted-foreground">
              Status: {invoice.status} (not yet sent)
            </span>
          )}
        </section>

        <p className="text-xs text-muted-foreground text-center pt-4">
          For questions about this invoice, contact {invoice.tenant.name}.
        </p>
      </div>
    </div>
  );
}
