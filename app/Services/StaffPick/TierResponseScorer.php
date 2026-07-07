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

        return $eligible
            ->sort(fn (Provider $a, Provider $b): int => $this->compare($a, $b, $case, $maxPriority))
            ->values();
    }

    /**
     * Strict lexicographic comparison, best-first. Each signal is evaluated in precedence
     * order; the first non-tie decides, and lower signals only break ties above them.
     * Add a new factor by inserting one `?:` clause at the precedence it deserves.
     */
    private function compare(Provider $a, Provider $b, IntakeRequest $case, int $maxPriority): int
    {
        return $this->requestedRank($b, $case) <=> $this->requestedRank($a, $case)
            ?: $this->preferredRank($b) <=> $this->preferredRank($a)
            ?: $this->tierRank($b, $maxPriority) <=> $this->tierRank($a, $maxPriority)
            ?: $this->responseRate($b) <=> $this->responseRate($a);
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
     * Accepted ÷ received across all of this provider's sent offers. Cold start: a provider
     * with zero offers received scores 1.0 (a perfect start), so new providers are surfaced
     * until they build a real history rather than sinking to the bottom on 0.0.
     *
     * Note: one count pair per provider per ordering — fine for the small eligible pools
     * we see. Precompute in a single grouped query if pools ever grow.
     */
    private function responseRate(Provider $provider): float
    {
        $received = $provider->assignmentOffers()->whereNotNull('offered_at')->count();

        if ($received === 0) {
            return 1.0;
        }

        $accepted = $provider->assignmentOffers()
            ->where('status', AssignmentOffer::STATUS_ACCEPTED)
            ->count();

        return $accepted / $received;
    }
}
