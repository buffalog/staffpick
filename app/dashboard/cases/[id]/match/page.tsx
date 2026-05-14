import { notFound } from "next/navigation";
import Link from "next/link";
import { withSession } from "@/lib/with-session";
import { prisma } from "@/lib/tenant-context";
import {
  findCandidates,
  type CandidateProvider,
} from "@/lib/matching/find-candidates";
import { PHASE_LABELS } from "@/lib/case-state-machine";
import type { CasePhase } from "@/lib/enums";
import { approveMatch } from "./actions";

export const dynamic = "force-dynamic";

const DAY_NAMES = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function formatMinute(min: number): string {
  const h = Math.floor(min / 60);
  const m = min % 60;
  const am = h < 12;
  const h12 = ((h + 11) % 12) + 1;
  return `${h12}${m === 0 ? "" : `:${String(m).padStart(2, "0")}`}${am ? "a" : "p"}`;
}

function summarizeAvailability(byDay: Map<number, Array<[number, number]>>): string {
  const parts: string[] = [];
  for (let d = 0; d < 7; d++) {
    const slots = byDay.get(d);
    if (!slots || slots.length === 0) continue;
    const ranges = slots.map(([s, e]) => `${formatMinute(s)}–${formatMinute(e)}`).join(", ");
    parts.push(`${DAY_NAMES[d]} ${ranges}`);
  }
  return parts.length === 0 ? "No availability on file" : parts.join(" · ");
}

export default async function MatchPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  const data = await withSession(async (ctx) => {
    const request = await prisma.intakeRequest.findFirst({
      where: { id },
      include: {
        subject: true,
        source: true,
        diagnoses: { orderBy: { is_primary: "desc" } },
        provider_assignments: { include: { provider: true } },
      },
    });
    if (!request) return null;

    // Pull tenant weights from settings
    const weightSettings = await prisma.tenantSetting.findMany({
      where: { key: { in: ["matching_weight_availability", "matching_weight_proximity"] } },
    });
    const availWeight = weightSettings.find((s) => s.key === "matching_weight_availability");
    const proxWeight = weightSettings.find((s) => s.key === "matching_weight_proximity");
    const weights = {
      availability: availWeight ? Number(availWeight.value) : 0.6,
      proximity: proxWeight ? Number(proxWeight.value) : 0.4,
    };

    // Fetch all active providers with availability + addresses
    const providersRaw = await prisma.provider.findMany({
      where: { active: true },
      include: { availability: true, addresses: true },
    });
    const candidates: CandidateProvider[] = providersRaw.map((p) => {
      const byDay = new Map<number, Array<[number, number]>>();
      for (const a of p.availability) {
        const slots = byDay.get(a.day_of_week) ?? [];
        slots.push([a.start_minute, a.end_minute]);
        byDay.set(a.day_of_week, slots);
      }
      return {
        id: p.id,
        given_name: p.given_name,
        family_name: p.family_name,
        specialty: p.specialty,
        provider_type: p.provider_type,
        active: p.active,
        availabilityByDay: byDay,
        zip_codes: p.addresses.map((ad) => ad.postal_code),
      };
    });

    const ranked = findCandidates(
      {
        requested_service: request.requested_service,
        schedule_preference: request.schedule_preference,
        subject_zip: request.subject?.postal_code ?? null,
      },
      candidates,
      weights,
    );

    return { request, ranked, weights, ctx };
  });

  if (!data) notFound();
  const { request, ranked, weights } = data;

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <p className="text-xs text-muted-foreground">
          <Link href={`/dashboard/cases/${request.id}`} className="underline">
            ← Case
          </Link>
        </p>
        <h1 className="text-2xl font-semibold tracking-tight">
          Match candidates for{" "}
          {request.subject
            ? `${request.subject.given_name} ${request.subject.family_name}`
            : "(no subject)"}
        </h1>
        <p className="text-sm text-muted-foreground">
          Phase: <span className="font-mono">{PHASE_LABELS[request.phase as CasePhase]}</span>
          {" · "}
          Requested service: {request.requested_service ?? "—"}
          {" · "}
          Subject zip: <span className="font-mono">{request.subject?.postal_code ?? "—"}</span>
          {" · "}
          Weights: avail {weights.availability.toFixed(2)} · prox {weights.proximity.toFixed(2)}
        </p>
      </header>

      {ranked.length === 0 ? (
        <div className="rounded-md border bg-card p-6 text-center text-sm text-muted-foreground">
          No active providers match the requested service. Adjust availability,
          add providers, or revise the referral.
        </div>
      ) : (
        <div className="space-y-3">
          {ranked.map((c, idx) => (
            <div
              key={c.provider.id}
              className="rounded-md border bg-card p-4 flex items-start justify-between gap-4"
            >
              <div className="space-y-1">
                <div className="flex items-baseline gap-2">
                  <span className="text-xs text-muted-foreground tabular-nums">#{idx + 1}</span>
                  <h2 className="font-medium">
                    {c.provider.given_name} {c.provider.family_name}
                  </h2>
                  <span className="text-xs text-muted-foreground">
                    {c.provider.specialty ?? c.provider.provider_type ?? ""} · match{" "}
                    <span className="font-mono">{c.matched_specialty}</span>
                  </span>
                </div>
                <div className="text-xs text-muted-foreground">
                  Score <span className="font-mono">{c.score.toFixed(3)}</span>
                  {" · "}
                  Availability fit{" "}
                  <span className="font-mono">{c.availability_fit.toFixed(2)}</span>
                  {" · "}
                  Proximity <span className="font-mono">{c.proximity_score.toFixed(2)}</span>
                  {" · "}
                  Weekly{" "}
                  <span className="font-mono">
                    {Math.round(c.weekly_minutes / 60)}h
                  </span>
                </div>
                <div className="text-xs">
                  <span className="text-muted-foreground">Avail: </span>
                  {summarizeAvailability(c.provider.availabilityByDay)}
                </div>
              </div>
              <form action={approveMatch.bind(null, request.id, c.provider.id)}>
                <button
                  type="submit"
                  className="rounded-md bg-primary text-primary-foreground px-3 py-1.5 text-xs font-medium hover:opacity-90"
                >
                  Approve match
                </button>
              </form>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
