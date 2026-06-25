<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use Illuminate\Support\Collection;

/**
 * Placeholder ordering for the match cascade.
 *
 *   score = (tier_rank * 100) + (response_rate * 100)
 *     tier_rank     = max_priority + 1 - tier.priority   (priority 1 = best = highest rank)
 *     response_rate = accepted offers / offers received  (0.0–1.0)
 *
 * tier_rank is derived from the tier's numeric priority — never the tier name — so the
 * Platinum=1…Bronze=4 priority data drives ranking unambiguously.
 *
 * // TODO: replace with the weighted scoring framework (distance, language, preference,
 * // recency, load, etc.). The interface is the seam — swap the binding, not this engine.
 */
class PlaceholderScorer implements ProviderScorer
{
    public function order(IntakeRequest $case, Collection $eligible): Collection
    {
        $maxPriority = (int) (ProviderTier::query()
            ->where('tenant_id', $case->tenant_id)
            ->max('priority') ?? 4);

        return $eligible
            ->sortByDesc(fn (Provider $provider): float => $this->score($provider, $maxPriority))
            ->values();
    }

    public function score(Provider $provider, int $maxPriority): float
    {
        $priority = $provider->tier?->priority ?? $maxPriority;
        $tierRank = $maxPriority + 1 - $priority;

        return ($tierRank * 100) + ($this->responseRate($provider) * 100);
    }

    /**
     * Accepted ÷ received across all of this provider's sent offers.
     *
     * ponytail: one count pair per eligible provider per dispatch — fine for the small
     * eligible pools we see. Precompute in a single grouped query if pools ever grow.
     */
    private function responseRate(Provider $provider): float
    {
        $received = $provider->assignmentOffers()->whereNotNull('offered_at')->count();

        if ($received === 0) {
            return 0.0;
        }

        $accepted = $provider->assignmentOffers()
            ->where('status', AssignmentOffer::STATUS_ACCEPTED)
            ->count();

        return $accepted / $received;
    }
}
