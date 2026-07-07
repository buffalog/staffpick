<?php

namespace Tests\Unit\StaffPick;

use App\Models\StaffPick\Provider;
use App\Services\StaffPick\MatchingResult;
use PHPUnit\Framework\TestCase;

class MatchingResultTest extends TestCase
{
    public function test_it_exposes_the_provider_distance_language_and_factors(): void
    {
        $provider = new Provider(['first_name' => 'Avery', 'last_name' => 'Stone']);

        $result = new MatchingResult(
            provider: $provider,
            distanceMiles: 4.219,
            languageMatched: true,
            languageWarning: false,
            factors: ['tier_priority' => 1, 'is_preferred' => true],
        );

        $this->assertSame($provider, $result->provider);
        $this->assertSame(4.219, $result->distanceMiles);
        $this->assertTrue($result->languageMatched);
        $this->assertFalse($result->languageWarning);
        $this->assertSame(1, $result->factors['tier_priority']);
        $this->assertTrue($result->factors['is_preferred']);
    }

    public function test_to_array_rounds_distance_and_exposes_flags(): void
    {
        $provider = new Provider(['first_name' => 'Avery', 'last_name' => 'Stone']);
        $provider->id = 42;

        $result = new MatchingResult($provider, 4.219, true, true, ['tier_priority' => 2]);

        $this->assertSame([
            'provider_id' => 42,
            'distance_miles' => 4.22,
            'language_matched' => true,
            'language_warning' => true,
            'factors' => ['tier_priority' => 2],
        ], $result->toArray());
    }
}
