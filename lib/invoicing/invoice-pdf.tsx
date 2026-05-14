/* eslint-disable @typescript-eslint/no-explicit-any */
import {
  Document,
  Page,
  Text,
  View,
  StyleSheet,
} from "@react-pdf/renderer";

const styles = StyleSheet.create({
  page: { padding: 36, fontSize: 10, fontFamily: "Helvetica" },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    marginBottom: 24,
    borderBottom: "1pt solid #d4d4d8",
    paddingBottom: 12,
  },
  brand: { fontSize: 18, fontWeight: 700 },
  brandSub: { fontSize: 9, color: "#71717a" },
  right: { textAlign: "right" },
  invNumber: { fontSize: 11, fontWeight: 700 },
  meta: { fontSize: 9, color: "#71717a", marginTop: 2 },
  twoCol: { flexDirection: "row", gap: 12, marginBottom: 24 },
  card: {
    flex: 1,
    border: "1pt solid #e4e4e7",
    borderRadius: 4,
    padding: 8,
  },
  cardLabel: {
    fontSize: 8,
    color: "#71717a",
    textTransform: "uppercase",
    letterSpacing: 1,
    marginBottom: 4,
  },
  table: { border: "1pt solid #e4e4e7", borderRadius: 4, marginBottom: 16 },
  thead: {
    flexDirection: "row",
    backgroundColor: "#f4f4f5",
    paddingVertical: 6,
    paddingHorizontal: 8,
    borderBottom: "1pt solid #e4e4e7",
    fontWeight: 700,
  },
  trow: {
    flexDirection: "row",
    paddingVertical: 6,
    paddingHorizontal: 8,
    borderBottom: "1pt solid #f4f4f5",
  },
  tfoot: {
    flexDirection: "row",
    backgroundColor: "#f4f4f5",
    paddingVertical: 8,
    paddingHorizontal: 8,
    fontWeight: 700,
  },
  cellDate: { width: 70 },
  cellService: { flex: 1 },
  cellProvider: { width: 120 },
  cellMin: { width: 36, textAlign: "right" },
  cellAmount: { width: 70, textAlign: "right" },
  totalLabel: { flex: 1, textAlign: "right", paddingRight: 8 },
  status: { fontSize: 9, marginTop: 4 },
  footer: { fontSize: 8, color: "#71717a", textAlign: "center", marginTop: 24 },
});

export type InvoicePdfData = {
  tenantName: string;
  invoice: {
    number: string;
    status: string;
    currency: string;
    total_cents: number;
    issued_at: Date | null;
    due_at: Date | null;
    paid_at: Date | null;
  };
  source: { name: string; email: string | null; contactName?: string | null };
  subject: { name: string };
  lines: Array<{
    visitDate: Date;
    serviceCode: string | null;
    description: string;
    provider: string;
    durationMinutes: number | null;
    amountCents: number;
  }>;
};

function fmtMoney(cents: number, currency: string): string {
  return `$${(cents / 100).toFixed(2)} ${currency}`;
}
function fmtDate(d: Date | null): string {
  return d ? d.toLocaleDateString() : "—";
}

export function InvoicePdfDocument(props: InvoicePdfData) {
  const { tenantName, invoice, source, subject, lines } = props;
  // @react-pdf/renderer's JSX types are strict; cast nodes through any to
  // sidestep noise from React 19 / RN style intrinsic mismatch.
  const D = Document as unknown as any;
  const P = Page as unknown as any;
  const V = View as unknown as any;
  const T = Text as unknown as any;
  return (
    <D>
      <P size="LETTER" style={styles.page}>
        <V style={styles.header}>
          <V>
            <T style={styles.brand}>{tenantName}</T>
            <T style={styles.brandSub}>Invoice</T>
          </V>
          <V style={styles.right}>
            <T style={styles.invNumber}>{invoice.number}</T>
            <T style={styles.meta}>
              {invoice.status} · issued {fmtDate(invoice.issued_at)}
            </T>
            {invoice.due_at ? (
              <T style={styles.meta}>Due {fmtDate(invoice.due_at)}</T>
            ) : null}
            {invoice.paid_at ? (
              <T style={styles.meta}>Paid {fmtDate(invoice.paid_at)}</T>
            ) : null}
          </V>
        </V>

        <V style={styles.twoCol}>
          <V style={styles.card}>
            <T style={styles.cardLabel}>Bill to</T>
            <T>{source.name}</T>
            {source.contactName ? <T>{source.contactName}</T> : null}
            <T style={styles.brandSub}>{source.email ?? "—"}</T>
          </V>
          <V style={styles.card}>
            <T style={styles.cardLabel}>For patient</T>
            <T>{subject.name}</T>
          </V>
        </V>

        <V style={styles.table}>
          <V style={styles.thead}>
            <T style={styles.cellDate}>Date</T>
            <T style={styles.cellService}>Service</T>
            <T style={styles.cellProvider}>Provider</T>
            <T style={styles.cellMin}>Min</T>
            <T style={styles.cellAmount}>Amount</T>
          </V>
          {lines.map((l, i) => (
            <V style={styles.trow} key={i}>
              <T style={styles.cellDate}>{fmtDate(l.visitDate)}</T>
              <V style={styles.cellService}>
                <T>{l.serviceCode ?? "—"}</T>
                <T style={styles.brandSub}>{l.description}</T>
              </V>
              <T style={styles.cellProvider}>{l.provider}</T>
              <T style={styles.cellMin}>
                {l.durationMinutes ?? "—"}
              </T>
              <T style={styles.cellAmount}>
                ${(l.amountCents / 100).toFixed(2)}
              </T>
            </V>
          ))}
          <V style={styles.tfoot}>
            <T style={styles.totalLabel}>Total</T>
            <T style={styles.cellAmount}>
              {fmtMoney(invoice.total_cents, invoice.currency)}
            </T>
          </V>
        </V>

        <T style={styles.footer}>
          For questions, contact {tenantName}.
        </T>
      </P>
    </D>
  );
}
