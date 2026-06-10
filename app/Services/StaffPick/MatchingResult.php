<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\Provider;

/**
 * The outcome of scoring a single eligible provider against an intake request:
 * the provider, its overall match score, the computed straight-line distance to
 * the subject, whether it matched the subject's language preference, a collection
 * level language warning (no eligible provider matched the requested language),
 * and the per-factor breakdown that produced the score.
 */
final class MatchingResult
{
    /**
     * @param  array<string, mixed>  $factors
     */
    public function __construct(
        public readonly Provider $provider,
        public readonly float $score,
        public readonly float $distanceMiles,
        public readonly bool $languageMatched = false,
        public readonly bool $languageWarning = false,
        public readonly array $factors = [],
    ) {}

    /**
     * @return array{provider_id: int|null, match_score: float, distance_miles: float, language_matched: bool, language_warning: bool, factors: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider->id,
            'match_score' => round($this->score, 4),
            'distance_miles' => round($this->distanceMiles, 2),
            'language_matched' => $this->languageMatched,
            'language_warning' => $this->languageWarning,
            'factors' => $this->factors,
        ];
    }
}
