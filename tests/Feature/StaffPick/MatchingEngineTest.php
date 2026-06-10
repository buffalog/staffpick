<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Specialty;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use Illuminate\Support\Collection;
use Tests\Feature\FeatureTest;

class MatchingEngineTest extends FeatureTest
{
    private const SUBJECT_LAT = 40.0;

    private const SUBJECT_LNG = -75.0;

    // Degrees of latitude per mile (north-south), used to place providers at a
    // known distance from the subject for deterministic distance assertions.
    private const MILES_PER_DEG_LAT = 69.094;

    private function engine(): MatchingEngine
    {
        return app(MatchingEngine::class);
    }

    private function makeTier(Tenant $tenant, string $name, int $priority): ProviderTier
    {
        return ProviderTier::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'priority' => $priority,
        ]);
    }

    private function makeSubject(Tenant $tenant, array $attributes = []): Subject
    {
        return Subject::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'latitude' => self::SUBJECT_LAT,
            'longitude' => self::SUBJECT_LNG,
        ], $attributes));
    }

    private function makeIntake(Tenant $tenant, Subject $subject, Discipline $discipline): IntakeRequest
    {
        return IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
        ]);
    }

    /**
     * Create a provider placed exactly $milesNorth miles north of the subject.
     */
    private function makeProvider(
        Tenant $tenant,
        Discipline $discipline,
        ProviderTier $tier,
        float $milesNorth,
        array $attributes = [],
    ): Provider {
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
    private function providerIds(Collection $results): array
    {
        return $results->map(fn (MatchingResult $r): int => $r->provider->id)->all();
    }

    public function test_excludes_providers_beyond_max_radius_plus_feathering(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);

        $inRange = $this->makeProvider($tenant, $discipline, $tier, 10);
        $tooFar = $this->makeProvider($tenant, $discipline, $tier, 30); // > 25 + 2 feathering

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $results = $this->engine()->match($intake);

        $this->assertSame([$inRange->id], $this->providerIds($results));
    }

    public function test_includes_near_miss_within_feathering_band_and_flags_it(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);

        $inside = $this->makeProvider($tenant, $discipline, $tier, 10);
        $nearMiss = $this->makeProvider($tenant, $discipline, $tier, 26); // 25 < d <= 27

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $results = $this->engine()->match($intake)->keyBy(fn (MatchingResult $r) => $r->provider->id);

        $this->assertTrue($results->has($nearMiss->id));
        $this->assertTrue($results->get($nearMiss->id)->factors['near_miss']);
        $this->assertFalse($results->get($inside->id)->factors['near_miss']);
    }

    public function test_excludes_providers_of_a_different_discipline(): void
    {
        $tenant = $this->createTenant();
        $pt = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $ot = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Occupational Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);

        $match = $this->makeProvider($tenant, $pt, $tier, 5);
        $this->makeProvider($tenant, $ot, $tier, 3); // closer, wrong discipline

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $pt);

        $results = $this->engine()->match($intake);

        $this->assertSame([$match->id], $this->providerIds($results));
    }

    public function test_excludes_inactive_providers(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);

        $active = $this->makeProvider($tenant, $discipline, $tier, 5);
        $this->makeProvider($tenant, $discipline, $tier, 3, ['is_active' => false, 'status' => 'inactive']);

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $results = $this->engine()->match($intake);

        $this->assertSame([$active->id], $this->providerIds($results));
    }

    public function test_orders_strictly_by_tier_then_proximity(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $gold = $this->makeTier($tenant, 'Gold', 1);
        $silver = $this->makeTier($tenant, 'Silver', 2);

        $goldNear = $this->makeProvider($tenant, $discipline, $gold, 5);
        $goldFar = $this->makeProvider($tenant, $discipline, $gold, 20);
        $silverClosest = $this->makeProvider($tenant, $discipline, $silver, 1); // closest overall, lower tier

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $results = $this->engine()->match($intake);

        // All tier-1 (Gold) before tier-2 (Silver), nearest-first within a tier.
        $this->assertSame([$goldNear->id, $goldFar->id, $silverClosest->id], $this->providerIds($results));
    }

    public function test_specialty_and_language_bonuses_raise_rank_among_equals(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);
        $specialty = Specialty::create(['tenant_id' => $tenant->id, 'name' => 'Pediatrics']);
        $spanish = Language::create(['name' => 'Spanish', 'code' => 'es']);

        // Both providers are the same distance from the subject.
        $plain = $this->makeProvider($tenant, $discipline, $tier, 10);
        $qualified = $this->makeProvider($tenant, $discipline, $tier, 10);
        $qualified->specialties()->attach($specialty->id);
        $qualified->languages()->attach($spanish->id, ['is_primary' => true]);

        $subject = $this->makeSubject($tenant, ['language_preference' => 'Spanish']);
        $intake = $this->makeIntake($tenant, $subject, $discipline);
        $intake->specialties()->attach($specialty->id);

        $results = $this->engine()->match($intake)->keyBy(fn (MatchingResult $r) => $r->provider->id);

        $this->assertSame([$qualified->id, $plain->id], $results->map(fn ($r) => $r->provider->id)->values()->all());
        $this->assertGreaterThan($results->get($plain->id)->score, $results->get($qualified->id)->score);
        $this->assertSame(1.0, $results->get($qualified->id)->factors['specialty']);
        $this->assertTrue($results->get($qualified->id)->factors['language']);
    }

    public function test_attaches_distance_and_score_to_each_result(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);
        $this->makeProvider($tenant, $discipline, $tier, 10);

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $result = $this->engine()->match($intake)->first();

        $this->assertEqualsWithDelta(10.0, $result->distanceMiles, 0.3);
        $this->assertGreaterThan(0.0, $result->score);
        $this->assertSame(1, $result->factors['tier_priority']);
    }

    public function test_returns_empty_when_subject_has_no_coordinates(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);
        $this->makeProvider($tenant, $discipline, $tier, 5);

        $subject = $this->makeSubject($tenant, ['latitude' => null, 'longitude' => null]);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $this->assertTrue($this->engine()->match($intake)->isEmpty());
    }

    public function test_feathering_band_respects_tenant_config(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create(['tenant_id' => $tenant->id, 'feathering_miles' => 0]);
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = $this->makeTier($tenant, 'Gold', 1);

        $inside = $this->makeProvider($tenant, $discipline, $tier, 24);
        $this->makeProvider($tenant, $discipline, $tier, 26); // beyond 25 + 0 feathering

        $subject = $this->makeSubject($tenant);
        $intake = $this->makeIntake($tenant, $subject, $discipline);

        $results = $this->engine()->match($intake);

        $this->assertSame([$inside->id], $this->providerIds($results));
    }
}
