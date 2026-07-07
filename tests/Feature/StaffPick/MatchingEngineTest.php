<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use Illuminate\Support\Collection;
use Tests\Feature\FeatureTest;

class MatchingEngineTest extends FeatureTest
{
    private const SUBJECT_LAT = 26.82; // North Palm Beach, FL

    private const SUBJECT_LNG = -80.05;

    private const MILES_PER_DEG_LAT = 69.094;

    private function engine(): MatchingEngine
    {
        return app(MatchingEngine::class);
    }

    private function discipline(Tenant $tenant, string $name = 'Physical Therapy'): Discipline
    {
        return Discipline::create(['tenant_id' => $tenant->id, 'name' => $name]);
    }

    private function tier(Tenant $tenant, string $name, int $priority): ProviderTier
    {
        return ProviderTier::create(['tenant_id' => $tenant->id, 'name' => $name, 'priority' => $priority]);
    }

    private function subject(Tenant $tenant, array $attributes = []): Subject
    {
        return Subject::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'latitude' => self::SUBJECT_LAT,
            'longitude' => self::SUBJECT_LNG,
            'provider_gender_preference' => null,
            'language_preference' => null,
        ], $attributes));
    }

    private function intake(Tenant $tenant, Subject $subject, Discipline $discipline): IntakeRequest
    {
        return IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
        ]);
    }

    private function provider(Tenant $tenant, Discipline $discipline, ProviderTier $tier, float $milesNorth, array $attributes = []): Provider
    {
        return Provider::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'discipline_id' => $discipline->id,
            'tier_id' => $tier->id,
            'latitude' => self::SUBJECT_LAT + ($milesNorth / self::MILES_PER_DEG_LAT),
            'longitude' => self::SUBJECT_LNG,
            'radius_max_miles' => 25,
            'status' => 'active',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @return array<int>
     */
    private function ids(Collection $results): array
    {
        return $results->map(fn (MatchingResult $r): int => $r->provider->id)->all();
    }

    public function test_excludes_providers_beyond_max_radius_plus_feathering(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $inRange = $this->provider($tenant, $discipline, $tier, 10);
        $this->provider($tenant, $discipline, $tier, 30); // > 25 + 2

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $this->assertSame([$inRange->id], $this->ids($this->engine()->match($intake)));
    }

    public function test_includes_provider_within_feathering_band(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $inside = $this->provider($tenant, $discipline, $tier, 10);
        $nearMiss = $this->provider($tenant, $discipline, $tier, 26); // 25 < d <= 27

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $this->assertEqualsCanonicalizing(
            [$inside->id, $nearMiss->id],
            $this->ids($this->engine()->match($intake)),
        );
    }

    public function test_excludes_providers_of_a_different_discipline(): void
    {
        $tenant = $this->createTenant();
        $pt = $this->discipline($tenant, 'Physical Therapy');
        $ot = $this->discipline($tenant, 'Occupational Therapy');
        $tier = $this->tier($tenant, 'Gold', 1);

        $match = $this->provider($tenant, $pt, $tier, 5);
        $this->provider($tenant, $ot, $tier, 3);

        $intake = $this->intake($tenant, $this->subject($tenant), $pt);

        $this->assertSame([$match->id], $this->ids($this->engine()->match($intake)));
    }

    public function test_multi_discipline_provider_matches_cases_in_any_held_discipline(): void
    {
        $tenant = $this->createTenant();
        $pt = $this->discipline($tenant, 'Physical Therapy');
        $ot = $this->discipline($tenant, 'Occupational Therapy');
        $tier = $this->tier($tenant, 'Gold', 1);

        // Dual-licensed provider: listed with PT as primary (discipline_id), OT added to
        // the set. This is the Petros case — previously invisible as an OT candidate.
        $dual = $this->provider($tenant, $pt, $tier, 5);
        $dual->disciplines()->attach($ot->id);

        $ptOnly = $this->provider($tenant, $pt, $tier, 20);

        // Eligible for a PT case, alongside the PT-only provider.
        $ptIntake = $this->intake($tenant, $this->subject($tenant), $pt);
        $this->assertEqualsCanonicalizing([$dual->id, $ptOnly->id], $this->ids($this->engine()->match($ptIntake)));

        // Eligible for an OT case, where the PT-only provider is not.
        $otIntake = $this->intake($tenant, $this->subject($tenant), $ot);
        $this->assertSame([$dual->id], $this->ids($this->engine()->match($otIntake)));
    }

    public function test_excludes_inactive_providers(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $active = $this->provider($tenant, $discipline, $tier, 5);
        $this->provider($tenant, $discipline, $tier, 3, ['is_active' => false, 'status' => 'inactive']);

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $this->assertSame([$active->id], $this->ids($this->engine()->match($intake)));
    }

    public function test_gender_preference_is_a_hard_filter(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $female = $this->provider($tenant, $discipline, $tier, 5, ['gender' => 'female']);
        $this->provider($tenant, $discipline, $tier, 2, ['gender' => 'male']);     // closer, wrong gender
        $this->provider($tenant, $discipline, $tier, 3, ['gender' => null]);       // no gender, no fallback

        $intake = $this->intake($tenant, $this->subject($tenant, ['provider_gender_preference' => 'female']), $discipline);

        $this->assertSame([$female->id], $this->ids($this->engine()->match($intake)));
    }

    public function test_no_gender_preference_admits_all_genders(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $this->provider($tenant, $discipline, $tier, 5, ['gender' => 'female']);
        $this->provider($tenant, $discipline, $tier, 6, ['gender' => 'male']);
        $this->provider($tenant, $discipline, $tier, 7, ['gender' => null]);

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $this->assertCount(3, $this->engine()->match($intake));
    }

    public function test_internal_rating_floor_excludes_low_but_passes_unrated(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create(['tenant_id' => $tenant->id, 'rating_internal_min' => 4.00]);
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $high = $this->provider($tenant, $discipline, $tier, 5, ['internal_rating' => 4.50]);
        $this->provider($tenant, $discipline, $tier, 6, ['internal_rating' => 3.50]); // below floor
        $unrated = $this->provider($tenant, $discipline, $tier, 7, ['internal_rating' => null]);

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $this->assertEqualsCanonicalizing([$high->id, $unrated->id], $this->ids($this->engine()->match($intake)));
    }

    public function test_patient_rating_floor_excludes_low_but_passes_unrated(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create(['tenant_id' => $tenant->id, 'rating_patient_min' => 4.00]);
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $high = $this->provider($tenant, $discipline, $tier, 5, ['rating_90day_avg' => 4.20]);
        $this->provider($tenant, $discipline, $tier, 6, ['rating_90day_avg' => 3.10]); // below floor
        $unrated = $this->provider($tenant, $discipline, $tier, 7, ['rating_90day_avg' => null]);

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $this->assertEqualsCanonicalizing([$high->id, $unrated->id], $this->ids($this->engine()->match($intake)));
    }

    public function test_language_is_a_hard_filter_when_a_speaker_is_eligible(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);
        $spanish = Language::create(['name' => 'Spanish', 'code' => 'es']);

        $this->provider($tenant, $discipline, $tier, 2); // closer, no Spanish — excluded
        $speaksSpanish = $this->provider($tenant, $discipline, $tier, 20);
        $speaksSpanish->languages()->attach($spanish->id, ['is_primary' => true]);

        $intake = $this->intake($tenant, $this->subject($tenant, ['language_preference' => 'Spanish']), $discipline);

        $results = $this->engine()->match($intake);

        // A language preference that CAN be satisfied excludes every non-speaker.
        $this->assertSame([$speaksSpanish->id], $this->ids($results));
        $this->assertTrue($results->first()->languageMatched);
        $this->assertFalse($results->first()->languageWarning);
    }

    public function test_language_warning_set_when_no_eligible_provider_speaks_the_language(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);

        $this->provider($tenant, $discipline, $tier, 5);
        $this->provider($tenant, $discipline, $tier, 8);

        $intake = $this->intake($tenant, $this->subject($tenant, ['language_preference' => 'Spanish']), $discipline);

        $results = $this->engine()->match($intake);

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn (MatchingResult $r) => $r->languageWarning));
        $this->assertTrue($results->every(fn (MatchingResult $r) => ! $r->languageMatched));
    }

    public function test_returns_empty_when_subject_has_no_coordinates(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);
        $this->provider($tenant, $discipline, $tier, 5);

        $intake = $this->intake($tenant, $this->subject($tenant, ['latitude' => null, 'longitude' => null]), $discipline);

        $this->assertTrue($this->engine()->match($intake)->isEmpty());
    }

    public function test_attaches_distance_and_flags_to_each_result(): void
    {
        $tenant = $this->createTenant();
        $discipline = $this->discipline($tenant);
        $tier = $this->tier($tenant, 'Gold', 1);
        $this->provider($tenant, $discipline, $tier, 10, ['is_preferred' => true]);

        $intake = $this->intake($tenant, $this->subject($tenant), $discipline);

        $result = $this->engine()->match($intake)->first();

        $this->assertEqualsWithDelta(10.0, $result->distanceMiles, 0.3);
        $this->assertSame(1, $result->factors['tier_priority']);
        $this->assertTrue($result->factors['is_preferred']);
    }
}
