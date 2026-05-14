/**
 * Pure matching ranker. Filters by specialty match (PT/OT/SLP — handles
 * combined requests like "PT+OT" too), then scores each candidate on
 *   score = availability_fit × W_avail + zip_proximity × W_proximity
 * where the weights come from tenant_settings (default 0.6 / 0.4).
 *
 * No DB calls — caller fetches Providers + availability + addresses, then
 * passes them in. Easy to unit-test, easy to swap the ranker out later.
 */

export type CandidateProvider = {
  id: string;
  given_name: string;
  family_name: string;
  specialty: string | null;
  provider_type: string | null;
  active: boolean;
  /** Day-of-week (0-6) → array of [startMin, endMin] tuples for that day */
  availabilityByDay: Map<number, Array<[number, number]>>;
  /** Postal codes from the provider's addresses */
  zip_codes: string[];
};

export type CandidateRequest = {
  /** "PT" | "OT" | "SLP" | "PT+OT" | "PT+OT+SLP" | "other" etc. */
  requested_service: string | null;
  /** Free-form schedule preference (used as a hint only for MVP) */
  schedule_preference: string | null;
  /** Subject's home zip — used for proximity */
  subject_zip: string | null;
};

export type Weights = {
  availability: number;
  proximity: number;
};

export const DEFAULT_WEIGHTS: Weights = { availability: 0.6, proximity: 0.4 };

export type ScoredCandidate = {
  provider: CandidateProvider;
  score: number;
  availability_fit: number;
  proximity_score: number;
  matched_specialty: string;
  weekly_minutes: number;
};

const ZIP_PREFIX_LENGTH = 3;

function requestedSpecialties(req: CandidateRequest): string[] {
  const raw = (req.requested_service ?? "").toUpperCase();
  if (!raw || raw === "OTHER") return [];
  return raw.split("+").map((s) => s.trim()).filter(Boolean);
}

function specialtyMatches(provider: CandidateProvider, wanted: string[]): string | null {
  if (wanted.length === 0) return provider.specialty ?? provider.provider_type ?? null;
  const have = [provider.specialty, provider.provider_type]
    .filter((v): v is string => Boolean(v))
    .map((v) => v.toUpperCase());
  for (const w of wanted) {
    if (have.includes(w)) return w;
  }
  return null;
}

/**
 * Availability fit is total weekly minutes available, normalized to [0,1]
 * against a baseline of 40 hours/week (2400 min). Providers with >40h get 1.0.
 * MVP doesn't attempt to align availability with the Subject's schedule_preference
 * — that's a future refinement.
 */
function availabilityFit(provider: CandidateProvider): {
  fit: number;
  weekly_minutes: number;
} {
  let totalMinutes = 0;
  for (const slots of provider.availabilityByDay.values()) {
    for (const [start, end] of slots) {
      if (end > start) totalMinutes += end - start;
    }
  }
  const baseline = 40 * 60; // 40 hours
  return { fit: Math.min(1, totalMinutes / baseline), weekly_minutes: totalMinutes };
}

/**
 * Zip-prefix proximity. Same N-prefix → 1.0 (very close), exactly the same
 * zip → 1.0, otherwise scaled by how many leading digits match.
 * MVP heuristic only — geocoded distance is post-MVP.
 */
function zipProximity(providerZips: string[], subjectZip: string | null): number {
  if (!subjectZip || providerZips.length === 0) return 0;
  const subj = subjectZip.replace(/\D/g, "");
  let best = 0;
  for (const z of providerZips) {
    const p = z.replace(/\D/g, "");
    let matchedDigits = 0;
    for (let i = 0; i < Math.min(p.length, subj.length); i++) {
      if (p[i] === subj[i]) matchedDigits++;
      else break;
    }
    const ratio = matchedDigits / Math.max(ZIP_PREFIX_LENGTH, subj.length);
    const clamped = Math.min(1, ratio);
    if (clamped > best) best = clamped;
  }
  return best;
}

export function findCandidates(
  request: CandidateRequest,
  providers: CandidateProvider[],
  weights: Weights = DEFAULT_WEIGHTS,
): ScoredCandidate[] {
  const wanted = requestedSpecialties(request);
  const wSum = weights.availability + weights.proximity || 1;
  const wAvail = weights.availability / wSum;
  const wProx = weights.proximity / wSum;

  const scored: ScoredCandidate[] = [];
  for (const p of providers) {
    if (!p.active) continue;
    const matched = specialtyMatches(p, wanted);
    if (matched === null) continue;
    const { fit, weekly_minutes } = availabilityFit(p);
    const proximity = zipProximity(p.zip_codes, request.subject_zip);
    const score = fit * wAvail + proximity * wProx;
    scored.push({
      provider: p,
      score,
      availability_fit: fit,
      proximity_score: proximity,
      matched_specialty: matched,
      weekly_minutes,
    });
  }
  // Highest score first; tie-break on weekly_minutes desc, then by family_name
  scored.sort((a, b) => {
    if (b.score !== a.score) return b.score - a.score;
    if (b.weekly_minutes !== a.weekly_minutes)
      return b.weekly_minutes - a.weekly_minutes;
    return a.provider.family_name.localeCompare(b.provider.family_name);
  });
  return scored;
}
