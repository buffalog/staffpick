<?php

namespace Tests\Unit\StaffPick;

use App\Models\StaffPick\Provider;
use App\Services\StaffPick\MatchingResult;
use PHPUnit\Framework\TestCase;

class MatchingResultTest extends TestCase
{
    public function test_it_exposes_the_provider_score_distance_and_factors(): void
    {
        $provider = new Provider(['first_name' => 'Avery', 'last_name' => 'Stone']);

        $result = new MatchingResult(
            provider: $provider,
            score: 0.873456,
            distanceMiles: 4.219,
            factors: ['tier_priority' => 1, 'near_miss' => false],
        );

        $this->assertSame($provider, $result->provider);
        $this->assertSame(0.873456, $result->score);
        $this->assertSame(4.219, $result->distanceMiles);
        $this->assertSame(1, $result->factors['tier_priority']);
        $this->assertFalse($result->factors['near_miss']);
    }

    public function test_to_array_rounds_score_and_distance_for_presentation(): void
    {
        $provider = new Provider(['first_name' => 'Avery', 'last_name' => 'Stone']);
        $provider->id = 42;

        $result = new MatchingResult($provider, 0.873456, 4.219, ['tier_priority' => 2]);

        $this->assertSame([
            'provider_id' => 42,
            'score' => 0.8735,
            'distance_miles' => 4.22,
            'factors' => ['tier_priority' => 2],
        ], $result->toArray());
    }
}
