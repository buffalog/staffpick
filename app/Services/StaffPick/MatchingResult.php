<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\Provider;

/**
 * The outcome of scoring a single eligible provider against an intake request:
 * the provider, its overall match score, the computed straight-line distance to
 * the subject, and the per-factor breakdown that produced the score.
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
        public readonly array $factors = [],
    ) {}

    /**
     * @return array{provider_id: int|null, score: float, distance_miles: float, factors: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider->id,
            'score' => round($this->score, 4),
            'distance_miles' => round($this->distanceMiles, 2),
            'factors' => $this->factors,
        ];
    }
}
