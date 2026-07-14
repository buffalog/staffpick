<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\Provider;

/**
 * The outcome of running a single provider through {@see MatchingEngine}: the
 * provider, its straight-line distance to the subject, whether it matched the
 * subject's language preference (informational), a per-row language warning (a
 * preference was stated and THIS provider does not speak it), and the per-factor
 * breakdown (out_of_radius, is_preferred, requested, tier_priority).
 *
 * This class carries eligibility only — no score, no order. Ordering is owned by
 * {@see ProviderScorer}.
 */
final class MatchingResult
{
    /**
     * @param  array<string, mixed>  $factors
     */
    public function __construct(
        public readonly Provider $provider,
        public readonly float $distanceMiles,
        public readonly bool $languageMatched = false,
        public readonly bool $languageWarning = false,
        public readonly array $factors = [],
    ) {}

    /**
     * @return array{provider_id: int|null, distance_miles: float, language_matched: bool, language_warning: bool, factors: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider->id,
            'distance_miles' => round($this->distanceMiles, 2),
            'language_matched' => $this->languageMatched,
            'language_warning' => $this->languageWarning,
            'factors' => $this->factors,
        ];
    }
}
