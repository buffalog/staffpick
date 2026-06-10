<?php

namespace Tests\Unit\StaffPick;

use App\Services\StaffPick\MatchingEngine;
use PHPUnit\Framework\TestCase;

class MatchingScoringTest extends TestCase
{
    public function test_haversine_distance_is_zero_for_identical_points(): void
    {
        $this->assertSame(0.0, MatchingEngine::distanceMiles(40.0, -75.0, 40.0, -75.0));
    }

    public function test_haversine_distance_matches_known_long_distance(): void
    {
        // New York City -> Los Angeles, great-circle ~2445 miles.
        $miles = MatchingEngine::distanceMiles(40.7128, -74.0060, 34.0522, -118.2437);

        $this->assertEqualsWithDelta(2445, $miles, 15);
    }

    public function test_haversine_distance_matches_known_short_distance(): void
    {
        // 0.1 degree of latitude is ~6.9 miles anywhere.
        $miles = MatchingEngine::distanceMiles(40.0, -75.0, 40.1, -75.0);

        $this->assertEqualsWithDelta(6.9, $miles, 0.2);
    }

    public function test_distance_score_is_one_at_origin_and_zero_at_cutoff(): void
    {
        $this->assertSame(1.0, MatchingEngine::scoreDistance(0.0, 27.0));
        $this->assertSame(0.0, MatchingEngine::scoreDistance(27.0, 27.0));
        $this->assertEqualsWithDelta(0.5, MatchingEngine::scoreDistance(13.5, 27.0), 0.0001);
    }

    public function test_distance_score_clamps_to_zero_beyond_cutoff(): void
    {
        $this->assertSame(0.0, MatchingEngine::scoreDistance(40.0, 27.0));
    }

    public function test_specialty_score_is_fraction_of_requested_specialties_matched(): void
    {
        $this->assertSame(0.5, MatchingEngine::scoreSpecialty([1, 2], [2, 3]));
        $this->assertSame(1.0, MatchingEngine::scoreSpecialty([1, 2], [1, 2, 3]));
        $this->assertSame(0.0, MatchingEngine::scoreSpecialty([1], [2, 3]));
    }

    public function test_specialty_score_is_zero_when_none_requested(): void
    {
        $this->assertSame(0.0, MatchingEngine::scoreSpecialty([], [1, 2]));
    }

    public function test_language_match_is_case_insensitive_on_name_or_code(): void
    {
        $providerLanguages = ['English', 'en', 'Spanish', 'es'];

        $this->assertSame(1.0, MatchingEngine::scoreLanguage('Spanish', $providerLanguages));
        $this->assertSame(1.0, MatchingEngine::scoreLanguage('spanish', $providerLanguages));
        $this->assertSame(1.0, MatchingEngine::scoreLanguage('es', $providerLanguages));
        $this->assertSame(0.0, MatchingEngine::scoreLanguage('French', $providerLanguages));
    }

    public function test_language_match_is_zero_without_a_preference(): void
    {
        $this->assertSame(0.0, MatchingEngine::scoreLanguage(null, ['English', 'en']));
        $this->assertSame(0.0, MatchingEngine::scoreLanguage('', ['English', 'en']));
    }

    public function test_compose_score_adds_weighted_specialty_and_language_bonuses_to_distance(): void
    {
        // distance 0.8 + 0.25 * specialty(0.5) + 0.15 * language(1.0) = 1.075
        $score = MatchingEngine::composeScore(0.8, 0.5, 1.0);

        $this->assertEqualsWithDelta(1.075, $score, 0.0001);
    }

    public function test_compose_score_is_distance_only_with_no_bonuses(): void
    {
        $this->assertSame(0.8, MatchingEngine::composeScore(0.8, 0.0, 0.0));
    }
}
