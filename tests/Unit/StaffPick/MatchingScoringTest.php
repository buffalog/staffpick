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

    public function test_gender_filter_passes_when_subject_has_no_preference(): void
    {
        $this->assertTrue(MatchingEngine::matchesGender(null, 'male'));
        $this->assertTrue(MatchingEngine::matchesGender('', null));
    }

    public function test_gender_filter_requires_exact_match_when_preference_is_set(): void
    {
        $this->assertTrue(MatchingEngine::matchesGender('female', 'female'));
        $this->assertTrue(MatchingEngine::matchesGender('Female', 'female')); // case-insensitive
        $this->assertFalse(MatchingEngine::matchesGender('female', 'male'));
        $this->assertFalse(MatchingEngine::matchesGender('female', null)); // no fallback for unset gender
    }

    public function test_rating_floor_passes_when_no_floor_or_no_rating(): void
    {
        $this->assertTrue(MatchingEngine::passesRatingFloor(2.0, null)); // tenant sets no floor
        $this->assertTrue(MatchingEngine::passesRatingFloor(null, 4.0)); // unrated provider passes
    }

    public function test_rating_floor_excludes_providers_below_the_floor(): void
    {
        $this->assertTrue(MatchingEngine::passesRatingFloor(4.0, 4.0));  // meets exactly
        $this->assertTrue(MatchingEngine::passesRatingFloor(4.5, 4.0));  // exceeds
        $this->assertFalse(MatchingEngine::passesRatingFloor(3.99, 4.0)); // below
    }

    public function test_language_matches_case_insensitively_on_name_or_code(): void
    {
        $providerLanguages = ['English', 'en', 'Spanish', 'es'];

        $this->assertTrue(MatchingEngine::languageMatches('Spanish', $providerLanguages));
        $this->assertTrue(MatchingEngine::languageMatches('spanish', $providerLanguages));
        $this->assertTrue(MatchingEngine::languageMatches('es', $providerLanguages));
        $this->assertFalse(MatchingEngine::languageMatches('French', $providerLanguages));
    }

    public function test_language_matches_is_false_without_a_preference(): void
    {
        $this->assertFalse(MatchingEngine::languageMatches(null, ['English', 'en']));
        $this->assertFalse(MatchingEngine::languageMatches('', ['English', 'en']));
    }

    public function test_compose_score_adds_a_heavy_bonus_for_a_language_match(): void
    {
        $withMatch = MatchingEngine::composeScore(0.5, true);
        $withoutMatch = MatchingEngine::composeScore(0.5, false);

        $this->assertSame(0.5, $withoutMatch);
        $this->assertSame(0.5 + MatchingEngine::LANGUAGE_BONUS, $withMatch);
        // Heavy enough that any language match outranks any proximity gap within a tier.
        $this->assertGreaterThan(MatchingEngine::composeScore(1.0, false), MatchingEngine::composeScore(0.0, true));
    }
}
