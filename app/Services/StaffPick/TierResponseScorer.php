<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use Illuminate\Support\Collection;

/**
 * The single source of provider ordering for both the manual Find Matches modal and
 * the automated cascade. Given the already-eligible providers from {@see MatchingEngine},
 * it orders them best-first.
 *
 * Ordering is a STRICT LEXICOGRAPHIC comparator, not a single additive score. Signals
 * are ranked by precedence; a higher-precedence signal always decides the order and the
 * next signal is only consulted to break a tie:
 *
 *   1. Referral-requested provider (case.requested_provider_id) — always first.
 *   2. is_preferred.
 *   3. Tier rank (max_priority + 1 - tier.priority; no tier = the tenant's worst tier).
 *   4. Response rate (accepted ÷ received; a provider with zero offers scores 1.0).
 *   5. Distance (closest first), lowest precedence — only breaks a full tie above.
 *
 * Why lexicographic and not summed weights: every future factor — exit-survey ratings,
 * paperwork/documentation timeliness, whatever else gets added — becomes a ONE-LINE
 * change: either a new clause in {@see compare()} at the precedence it deserves, or a
 * refinement folded into the response_rate tiebreak. No reasoning about the relative
 * magnitude of one factor's weight against every other factor across a whole formula.
 *
 * Tier + response-rate is v1, NOT the final design. It is the minimum that produces one
 * true order across both dispatch paths; the comparator is the seam that keeps growing it cheap.
 */
class TierResponseScorer implements ProviderScorer
{
    /**
     * Fallback worst priority when the tenant has no tiers configured (Platinum=1…Bronze=4).
     */
    private const DEFAULT_MAX_PRIORITY = 4;

    public function order(IntakeRequest $case, Collection $eligible): Collection
    {
        $maxPriority = (int) (ProviderTier::query()
            ->where('tenant_id', $case->tenant_id)
            ->max('priority') ?? self::DEFAULT_MAX_PRIORITY);

        $rates = $this->responseRates($case, $eligible);
        $distances = $this->distances($case, $eligible);

        return $eligible
            ->sort(fn (Provider $a, Provider $b): int => $this->compare($a, $b, $case, $maxPriority, $rates, $distances))
            ->values();
    }

    /**
     * Strict lexicographic comparison, best-first. Each signal is evaluated in precedence
     * order; the first non-tie decides, and lower signals only break ties above them.
     * Add a new factor by inserting one `?:` clause at the precedence it deserves.
     *
     * @param  array<int, float>  $rates  provider id => response rate (see responseRates())
     * @param  array<int, float>  $distances  provider id => miles from the subject (see distances())
     */
    private function compare(Provider $a, Provider $b, IntakeRequest $case, int $maxPriority, array $rates, array $distances): int
    {
        return $this->requestedRank($b, $case) <=> $this->requestedRank($a, $case)
            ?: $this->preferredRank($b) <=> $this->preferredRank($a)
            ?: $this->tierRank($b, $maxPriority) <=> $this->tierRank($a, $maxPriority)
            ?: $this->rateOf($rates, $b) <=> $this->rateOf($rates, $a)
            ?: $this->distanceOf($distances, $a) <=> $this->distanceOf($distances, $b); // closest first
    }

    /**
     * @param  array<int, float>  $rates
     */
    private function rateOf(array $rates, Provider $provider): float
    {
        return $rates[(int) $provider->id] ?? 1.0; // cold start: no resolved offers = perfect
    }

    /**
     * @param  array<int, float>  $distances
     */
    private function distanceOf(array $distances, Provider $provider): float
    {
        return $distances[(int) $provider->id] ?? 0.0; // no basis => neutral, tiebreak no-ops
    }

    /**
     * 1 for the referral-requested provider, else 0. Cast both sides — the pdo_sqlsrv
     * driver returns FK columns as strings, so a strict === would miss the match on Railway.
     */
    private function requestedRank(Provider $provider, IntakeRequest $case): int
    {
        return $case->requested_provider_id !== null
            && (int) $provider->id === (int) $case->requested_provider_id ? 1 : 0;
    }

    private function preferredRank(Provider $provider): int
    {
        return $provider->is_preferred ? 1 : 0;
    }

    /**
     * Higher rank = better tier. A provider with no tier is treated as the tenant's worst.
     */
    private function tierRank(Provider $provider, int $maxPriority): int
    {
        $priority = (int) ($provider->tier?->priority ?? $maxPriority);

        return $maxPriority + 1 - $priority;
    }

    /**
     * Response rate per provider in ONE grouped query, keyed by provider id. Replaces the
     * per-provider count pair that used to fire inside compare() (2 queries × O(n log n)
     * comparisons — a 40-provider pool was hundreds of round-trips).
     *
     * Denominator is RESOLVED offers only — accepted, declined, expired. Pending (not yet
     * answered) and withdrawn (the system pulled it when another provider took the case) are
     * excluded: the provider had no fair chance to respond, so counting them would sink their
     * rate for something that isn't their doing. Cold start (no resolved offers) is handled at
     * read in rateOf(): absent from the map => 1.0.
     *
     * pdo_sqlsrv returns aggregates as STRINGS — cast every count to int before dividing.
     *
     * @param  Collection<int, Provider>  $eligible
     * @return array<int, float> provider id => response rate
     */
    private function responseRates(IntakeRequest $case, Collection $eligible): array
    {
        $ids = $eligible->map(fn (Provider $p): int => (int) $p->id)->all();

        if ($ids === []) {
            return [];
        }

        return AssignmentOffer::query()
            ->select('provider_id')
            ->selectRaw('COUNT(*) as received')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted', [AssignmentOffer::STATUS_ACCEPTED])
            ->where('tenant_id', $case->tenant_id)
            ->whereIn('provider_id', $ids)
            ->whereIn('status', [
                AssignmentOffer::STATUS_ACCEPTED,
                AssignmentOffer::STATUS_DECLINED,
                AssignmentOffer::STATUS_EXPIRED,
            ])
            ->groupBy('provider_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->provider_id => (int) $row->accepted / (int) $row->received,
            ])
            ->all();
    }

    /**
     * Straight-line miles from the case subject to each provider, keyed by provider id.
     * Lowest-precedence tiebreak: when everything above ties, closest wins. Pure function of
     * coords the scorer already has, so the interface stays untouched. Subject ungeocoded =>
     * empty map => the tiebreak no-ops (distanceOf defaults to neutral).
     *
     * @param  Collection<int, Provider>  $eligible
     * @return array<int, float>
     */
    private function distances(IntakeRequest $case, Collection $eligible): array
    {
        $subject = $case->subject;

        if ($subject?->latitude === null || $subject?->longitude === null) {
            return [];
        }

        $lat = (float) $subject->latitude;
        $lng = (float) $subject->longitude;

        return $eligible->mapWithKeys(fn (Provider $p): array => [
            (int) $p->id => ($p->latitude === null || $p->longitude === null)
                ? PHP_FLOAT_MAX
                : MatchingEngine::distanceMiles($lat, $lng, (float) $p->latitude, (float) $p->longitude),
        ])->all();
    }
}
