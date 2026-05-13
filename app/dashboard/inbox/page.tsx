import Link from "next/link";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { PHASE_LABELS } from "@/lib/case-state-machine";
import type { CasePhase } from "@/lib/enums";
import { acceptRequest, rejectRequest } from "./actions";

export const dynamic = "force-dynamic";

export default async function IntakeInboxPage() {
  const requests = await withSession(async () => {
    return prisma.intakeRequest.findMany({
      where: {
        phase: { in: ["Phase1_IntakeReceived", "Phase2_IntakeReview"] },
        status: "Active",
      },
      orderBy: { created_at: "desc" },
      include: {
        subject: true,
        source: true,
        diagnoses: { orderBy: { is_primary: "desc" } },
      },
      take: 25,
    });
  });

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Intake Inbox</h1>
        <p className="text-sm text-muted-foreground">
          New referrals awaiting review. Accept to begin matching, or reject to cancel.
        </p>
      </header>

      {requests.length === 0 ? (
        <div className="rounded-md border bg-card p-6 text-center text-sm text-muted-foreground">
          No pending referrals.
        </div>
      ) : (
        <div className="overflow-hidden rounded-md border bg-card">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="px-3 py-2 font-medium">Received</th>
                <th className="px-3 py-2 font-medium">Patient</th>
                <th className="px-3 py-2 font-medium">Source</th>
                <th className="px-3 py-2 font-medium">Service</th>
                <th className="px-3 py-2 font-medium">Phase</th>
                <th className="px-3 py-2 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {requests.map((r) => {
                const primary = r.diagnoses.find((d) => d.is_primary) ?? r.diagnoses[0];
                return (
                  <tr key={r.id} className="hover:bg-accent/30">
                    <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                      {r.created_at.toLocaleDateString()}{" "}
                      <span className="text-xs">
                        {r.created_at.toLocaleTimeString([], {
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <Link
                        href={`/dashboard/cases/${r.id}`}
                        className="font-medium underline-offset-2 hover:underline"
                      >
                        {r.subject ? `${r.subject.family_name}, ${r.subject.given_name}` : "—"}
                      </Link>
                      {primary && (
                        <div className="text-xs text-muted-foreground font-mono">
                          {primary.code}
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-2">{r.source?.name ?? "—"}</td>
                    <td className="px-3 py-2">{r.requested_service ?? "—"}</td>
                    <td className="px-3 py-2 text-xs">
                      {PHASE_LABELS[r.phase as CasePhase] ?? r.phase}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex justify-end gap-2">
                        <form action={acceptRequest.bind(null, r.id)}>
                          <button
                            type="submit"
                            className="rounded-md border bg-primary text-primary-foreground px-3 py-1 text-xs font-medium hover:opacity-90"
                          >
                            Accept
                          </button>
                        </form>
                        <form action={rejectRequest.bind(null, r.id, undefined)}>
                          <button
                            type="submit"
                            className="rounded-md border px-3 py-1 text-xs font-medium hover:bg-accent"
                          >
                            Reject
                          </button>
                        </form>
                      </div>
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
