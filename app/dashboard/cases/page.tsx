import Link from "next/link";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { PHASE_LABELS } from "@/lib/case-state-machine";
import type { CasePhase } from "@/lib/enums";

export const dynamic = "force-dynamic";

type SearchParams = Promise<{ status?: string; phase?: string }>;

export default async function CasesListPage({
  searchParams,
}: {
  searchParams: SearchParams;
}) {
  const { status, phase } = await searchParams;
  const statusFilter = status ?? "Active";
  const phaseFilter = phase && phase !== "all" ? phase : null;

  const cases = await withSession(async () => {
    return prisma.intakeRequest.findMany({
      where: {
        status: statusFilter === "all" ? undefined : statusFilter,
        ...(phaseFilter ? { phase: phaseFilter } : {}),
      },
      orderBy: { updated_at: "desc" },
      include: {
        subject: true,
        source: true,
        provider_assignments: {
          where: { approved: true },
          include: { provider: true },
        },
        diagnoses: { where: { is_primary: true } },
      },
      take: 25,
    });
  });

  return (
    <div className="space-y-6">
      <header className="flex items-baseline justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Cases</h1>
          <p className="text-sm text-muted-foreground">
            All referrals — pending and in-flight. Use the Inbox for fresh
            referrals awaiting acceptance.
          </p>
        </div>
        <FilterBar status={statusFilter} phase={phase ?? "all"} />
      </header>

      {cases.length === 0 ? (
        <div className="rounded-md border bg-card p-6 text-center text-sm text-muted-foreground">
          No cases match the current filter.
        </div>
      ) : (
        <div className="overflow-hidden rounded-md border bg-card">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="px-3 py-2 font-medium">Patient</th>
                <th className="px-3 py-2 font-medium">Source</th>
                <th className="px-3 py-2 font-medium">Provider</th>
                <th className="px-3 py-2 font-medium">Phase</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Updated</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {cases.map((c) => {
                const assigned = c.provider_assignments[0]?.provider;
                return (
                  <tr key={c.id} className="hover:bg-accent/30">
                    <td className="px-3 py-2">
                      <Link
                        href={`/dashboard/cases/${c.id}`}
                        className="font-medium underline-offset-2 hover:underline"
                      >
                        {c.subject
                          ? `${c.subject.family_name}, ${c.subject.given_name}`
                          : "(no subject)"}
                      </Link>
                      {c.diagnoses[0] && (
                        <div className="text-xs text-muted-foreground font-mono">
                          {c.diagnoses[0].code}
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-2">{c.source?.name ?? "—"}</td>
                    <td className="px-3 py-2">
                      {assigned
                        ? `${assigned.given_name} ${assigned.family_name}`
                        : <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="px-3 py-2 text-xs">
                      {PHASE_LABELS[c.phase as CasePhase] ?? c.phase}
                    </td>
                    <td className="px-3 py-2 text-xs">{c.status}</td>
                    <td className="px-3 py-2 text-xs text-muted-foreground tabular-nums">
                      {c.updated_at.toLocaleDateString()}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function FilterBar({ status, phase }: { status: string; phase: string }) {
  const statuses = ["Active", "OnHold", "Cancelled", "Closed", "all"];
  const phases: Array<{ value: string; label: string }> = [
    { value: "all", label: "All phases" },
    ...(Object.entries(PHASE_LABELS) as Array<[CasePhase, string]>).map(
      ([v, l]) => ({ value: v, label: l }),
    ),
  ];
  return (
    <form className="flex items-center gap-2" action="/dashboard/cases">
      <select
        name="status"
        defaultValue={status}
        className="rounded-md border bg-background px-2 py-1 text-xs"
      >
        {statuses.map((s) => (
          <option key={s} value={s}>
            {s}
          </option>
        ))}
      </select>
      <select
        name="phase"
        defaultValue={phase}
        className="rounded-md border bg-background px-2 py-1 text-xs"
      >
        {phases.map((p) => (
          <option key={p.value} value={p.value}>
            {p.label}
          </option>
        ))}
      </select>
      <button
        type="submit"
        className="rounded-md border px-2 py-1 text-xs hover:bg-accent"
      >
        Apply
      </button>
    </form>
  );
}
