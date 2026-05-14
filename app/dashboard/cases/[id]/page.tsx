import { notFound } from "next/navigation";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import { prismaBase } from "@/lib/prisma";
import {
  PHASE_LABELS,
  legalNextPhases,
  legalNextStatuses,
  isTerminalPhase,
  isTerminalStatus,
} from "@/lib/case-state-machine";
import type { CasePhase, CaseStatus } from "@/lib/enums";
import { transitionPhase, transitionStatus } from "./actions";
import { startInitialAssessment } from "./assess/actions";
import { MessageThread } from "./messages/message-thread";

export const dynamic = "force-dynamic";

export default async function CaseDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  const { request, audit, messages, userEmail } = await withSession(async (ctx) => {
    const request = await prisma.intakeRequest.findFirst({
      where: { id },
      include: {
        subject: true,
        source: { include: { contacts: true } },
        diagnoses: { orderBy: { is_primary: "desc" } },
        caregivers: { include: { caregiver: true } },
        provider_assignments: { include: { provider: true } },
        resolution_plans: true,
        assessments: { orderBy: { performed_at: "desc" } },
      },
    });
    // UserActivityLog is not tenant-scoped (nullable tenant_id); filter manually.
    const audit = await prismaBase.userActivityLog.findMany({
      where: {
        tenant_id: ctx.tenantId,
        entity_type: "IntakeRequest",
        entity_id: id,
      },
      orderBy: { occurred_at: "desc" },
      take: 50,
    });
    const messages = await prisma.caseMessage.findMany({
      where: { request_id: id },
      orderBy: { created_at: "asc" },
      include: { sender: { select: { email: true, name: true } } },
      take: 200,
    });
    return { request, audit, messages, userEmail: ctx.email };
  });

  if (!request) notFound();

  const currentPhase = request.phase as CasePhase;
  const currentStatus = request.status as CaseStatus;
  const nextPhases = legalNextPhases(currentPhase);
  const nextStatuses = legalNextStatuses(currentStatus);

  return (
    <div className="space-y-6">
      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <header className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">
            {request.subject
              ? `${request.subject.given_name} ${request.subject.family_name}`
              : "(no subject)"}
          </h1>
          <p className="text-sm text-muted-foreground font-mono">{request.id}</p>
        </div>
        <div className="flex items-center gap-2">
          <StatusBadge status={currentStatus} />
          <PhaseBadge phase={currentPhase} />
        </div>
      </header>

      {/* ── Actions ────────────────────────────────────────────────────────── */}
      {!isTerminalPhase(currentPhase) || !isTerminalStatus(currentStatus) ? (
        <section className="rounded-md border bg-card p-4 space-y-3">
          <h2 className="text-sm font-medium">Actions</h2>
          <div className="flex flex-wrap gap-2">
            {(currentPhase === "Phase3_MatchingKickoff" ||
              currentPhase === "Phase4_MatchReview") && (
              <a
                href={`/dashboard/cases/${request.id}/match`}
                className="rounded-md bg-primary text-primary-foreground px-3 py-1.5 text-xs font-medium hover:opacity-90"
              >
                Find providers
              </a>
            )}
            {currentPhase === "Phase6_Collaboration" && (
              <form action={startInitialAssessment.bind(null, request.id)}>
                <button
                  type="submit"
                  className="rounded-md bg-primary text-primary-foreground px-3 py-1.5 text-xs font-medium hover:opacity-90"
                >
                  Start initial assessment
                </button>
              </form>
            )}
            {currentPhase === "Phase7_InitialAssessment" && (
              <a
                href={`/dashboard/cases/${request.id}/assess`}
                className="rounded-md bg-primary text-primary-foreground px-3 py-1.5 text-xs font-medium hover:opacity-90"
              >
                Record initial assessment
              </a>
            )}
            {nextPhases
              .filter((p) => p !== currentPhase) // hide self-loop button
              .map((p) => (
                <form key={p} action={transitionPhase.bind(null, request.id, p)}>
                  <button
                    type="submit"
                    className="rounded-md border bg-primary text-primary-foreground px-3 py-1.5 text-xs font-medium hover:opacity-90"
                  >
                    Advance to {PHASE_LABELS[p]}
                  </button>
                </form>
              ))}
            {nextStatuses
              .filter((s) => s !== currentStatus)
              .map((s) => (
                <form
                  key={s}
                  action={transitionStatus.bind(null, request.id, s)}
                >
                  <button
                    type="submit"
                    className="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-accent"
                  >
                    Set status: {s}
                  </button>
                </form>
              ))}
          </div>
        </section>
      ) : null}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* ── Subject ──────────────────────────────────────────────────────── */}
        <Card title="Patient">
          {request.subject ? (
            <dl className="grid grid-cols-3 gap-x-2 gap-y-1 text-sm">
              <dt className="text-muted-foreground">Name</dt>
              <dd className="col-span-2">
                {request.subject.given_name} {request.subject.family_name}
              </dd>
              <dt className="text-muted-foreground">DOB</dt>
              <dd className="col-span-2">
                {request.subject.date_of_birth?.toLocaleDateString() ?? "—"}
              </dd>
              <dt className="text-muted-foreground">Language</dt>
              <dd className="col-span-2">
                {request.subject.preferred_language ?? "—"}
              </dd>
              <dt className="text-muted-foreground">Address</dt>
              <dd className="col-span-2">
                {[
                  request.subject.address_line1,
                  request.subject.city,
                  request.subject.state,
                  request.subject.postal_code,
                ]
                  .filter(Boolean)
                  .join(", ") || "—"}
              </dd>
              <dt className="text-muted-foreground">Phone</dt>
              <dd className="col-span-2">{request.subject.phone ?? "—"}</dd>
            </dl>
          ) : (
            <p className="text-sm text-muted-foreground">No subject on file.</p>
          )}
        </Card>

        {/* ── Source ───────────────────────────────────────────────────────── */}
        <Card title="Referring source">
          {request.source ? (
            <dl className="grid grid-cols-3 gap-x-2 gap-y-1 text-sm">
              <dt className="text-muted-foreground">Org</dt>
              <dd className="col-span-2">{request.source.name}</dd>
              <dt className="text-muted-foreground">Email</dt>
              <dd className="col-span-2">{request.source.email ?? "—"}</dd>
              <dt className="text-muted-foreground">Phone</dt>
              <dd className="col-span-2">{request.source.phone ?? "—"}</dd>
              {request.source.contacts.length > 0 && (
                <>
                  <dt className="text-muted-foreground">Contact</dt>
                  <dd className="col-span-2">
                    {request.source.contacts[0].given_name}{" "}
                    {request.source.contacts[0].family_name}
                  </dd>
                </>
              )}
            </dl>
          ) : (
            <p className="text-sm text-muted-foreground">No source on file.</p>
          )}
        </Card>

        {/* ── Diagnoses ────────────────────────────────────────────────────── */}
        <Card title="Diagnoses">
          {request.diagnoses.length === 0 ? (
            <p className="text-sm text-muted-foreground">None recorded.</p>
          ) : (
            <ul className="space-y-1 text-sm">
              {request.diagnoses.map((d) => (
                <li key={d.id}>
                  <span className="font-mono font-semibold mr-2">{d.code}</span>
                  {d.description}
                  {d.is_primary && (
                    <span className="ml-2 text-xs uppercase tracking-wide text-muted-foreground">
                      primary
                    </span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>

        {/* ── CareGivers ───────────────────────────────────────────────────── */}
        <Card title="CareGivers">
          {request.caregivers.length === 0 ? (
            <p className="text-sm text-muted-foreground">None recorded.</p>
          ) : (
            <ul className="space-y-1 text-sm">
              {request.caregivers.map((cg) => (
                <li key={cg.id}>
                  {cg.caregiver.given_name} {cg.caregiver.family_name}
                  {cg.caregiver.phone && (
                    <span className="text-muted-foreground"> · {cg.caregiver.phone}</span>
                  )}
                  {cg.caregiver.relation_to_subject && (
                    <span className="text-muted-foreground">
                      {" "}
                      · {cg.caregiver.relation_to_subject}
                    </span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>

        {/* ── Service & schedule ───────────────────────────────────────────── */}
        <Card title="Service request">
          <dl className="grid grid-cols-3 gap-x-2 gap-y-1 text-sm">
            <dt className="text-muted-foreground">Requested</dt>
            <dd className="col-span-2">{request.requested_service ?? "—"}</dd>
            <dt className="text-muted-foreground">Schedule</dt>
            <dd className="col-span-2">{request.schedule_preference ?? "—"}</dd>
            <dt className="text-muted-foreground">Channel</dt>
            <dd className="col-span-2 font-mono text-xs">{request.ingestion_channel}</dd>
          </dl>
          {request.notes && (
            <div className="mt-3 rounded-md bg-muted/40 p-2 text-sm whitespace-pre-wrap">
              {request.notes}
            </div>
          )}
        </Card>

        {/* ── Provider assignments ─────────────────────────────────────────── */}
        <Card title="Provider assignments">
          {request.provider_assignments.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No providers assigned yet. Advance to Phase 3 to begin matching.
            </p>
          ) : (
            <ul className="space-y-1 text-sm">
              {request.provider_assignments.map((pa) => (
                <li key={pa.id}>
                  {pa.provider.given_name} {pa.provider.family_name}
                  <span className="text-muted-foreground">
                    {" "}
                    · {pa.provider.specialty ?? "—"}
                    {pa.approved && " · approved"}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      {/* ── Conversation ─────────────────────────────────────────────────────*/}
      <Card title="Conversation">
        <MessageThread
          requestId={request.id}
          initialMessages={messages.map((m) => ({
            id: m.id,
            body: m.body,
            senderEmail: m.sender.email,
            senderName: m.sender.name,
            createdAt: m.created_at.toISOString(),
          }))}
          currentUserEmail={userEmail}
        />
      </Card>

      {/* ── Timeline ─────────────────────────────────────────────────────────*/}
      <Card title="Activity timeline">
        {audit.length === 0 ? (
          <p className="text-sm text-muted-foreground">No activity recorded yet.</p>
        ) : (
          <ol className="space-y-2 text-sm">
            {audit.map((e) => (
              <li key={e.id} className="flex items-baseline gap-3">
                <span className="text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                  {e.occurred_at.toLocaleString()}
                </span>
                <span>
                  <span className="font-medium">{e.action}</span>
                  {e.metadata && (
                    <span className="ml-2 text-xs text-muted-foreground font-mono">
                      {e.metadata}
                    </span>
                  )}
                </span>
              </li>
            ))}
          </ol>
        )}
      </Card>
    </div>
  );
}

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-md border bg-card p-4 space-y-3">
      <h2 className="text-sm font-medium">{title}</h2>
      {children}
    </section>
  );
}

function PhaseBadge({ phase }: { phase: CasePhase }) {
  return (
    <span className="rounded-full bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 text-xs">
      {PHASE_LABELS[phase] ?? phase}
    </span>
  );
}

function StatusBadge({ status }: { status: CaseStatus }) {
  const color =
    status === "Active"
      ? "bg-emerald-500/10 text-emerald-700 border-emerald-500/30 dark:text-emerald-300"
      : status === "OnHold"
      ? "bg-amber-500/10 text-amber-700 border-amber-500/30 dark:text-amber-300"
      : status === "Cancelled"
      ? "bg-destructive/10 text-destructive border-destructive/30"
      : "bg-muted text-muted-foreground border-muted-foreground/20";
  return (
    <span className={`rounded-full px-2 py-0.5 text-xs border ${color}`}>
      {status}
    </span>
  );
}
