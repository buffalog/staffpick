<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use App\Services\StaffPick\TenantContext;
use Tests\Feature\FeatureTest;

/**
 * Permanent net under the "invisible provider" class of bug.
 *
 * MatchingEngine filters providers ONLY through the sp_provider_disciplines pivot, with no
 * fallback to the legacy sp_providers.discipline_id column. So any write path that sets
 * discipline_id but forgets to sync the pivot produces a provider who is silently, and
 * permanently, never offered work. The DemoDataSeeder did exactly that.
 *
 * Provider::saved() now backfills the pivot from discipline_id whenever the pivot is empty.
 * These tests pin that invariant at the model layer (so it holds for every writer, including
 * ones nobody has written yet) and end-to-end through the engine.
 */
class ProviderDisciplineBackfillTest extends FeatureTest
{
    private const SUBJECT_LAT = 26.82;

    private const SUBJECT_LNG = -80.05;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        // The matchability check runs MatchingEngine, which reads the case's Subject (PHI);
        // in production it always runs in a tenant context. All fixtures belong to $this->tenant.
        app(TenantContext::class)->set($this->tenant);
    }

    /**
     * Save a provider WITHOUT going through the factory's create() hooks. ProviderFactory's
     * configure() attaches the pivot in afterCreating, which would mask the model hook — so
     * make() (unsaved) + save() is what a naive writer like a seeder actually does.
     */
    private function saveProviderWithOnlyDisciplineId(Discipline $discipline, array $attributes = []): Provider
    {
        $provider = Provider::factory()->make(array_merge([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $discipline->id,
            'latitude' => self::SUBJECT_LAT,
            'longitude' => self::SUBJECT_LNG,
            'radius_max_miles' => 25,
            'status' => 'active',
            'is_active' => true,
        ], $attributes));

        $provider->save();

        return $provider;
    }

    public function test_saving_with_only_a_discipline_id_backfills_the_pivot(): void
    {
        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);

        $provider = $this->saveProviderWithOnlyDisciplineId($discipline);

        $pivot = $provider->disciplines()->get();

        $this->assertSame([$discipline->id], $pivot->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertTrue((bool) $pivot->first()->pivot->is_primary, 'The backfilled discipline should be primary.');
    }

    public function test_a_provider_saved_with_only_a_discipline_id_is_matchable(): void
    {
        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Occupational Therapy']);
        $tier = ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Gold', 'priority' => 1]);

        $provider = $this->saveProviderWithOnlyDisciplineId($discipline, ['tier_id' => $tier->id]);

        $subject = Subject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => self::SUBJECT_LAT,
            'longitude' => self::SUBJECT_LNG,
            'provider_gender_preference' => null,
            'language_preference' => null,
        ]);

        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
        ]);

        $matched = app(MatchingEngine::class)->match($intake)
            ->map(fn (MatchingResult $result): int => $result->provider->id)
            ->all();

        // Before the backfill hook this was [] — the provider existed, held the discipline on
        // the legacy column, and was still never offered the case.
        $this->assertContains($provider->id, $matched);
    }

    public function test_an_existing_pivot_is_never_rewritten(): void
    {
        $physical = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'PT Multi']);
        $speech = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'SLP Multi']);

        $provider = $this->saveProviderWithOnlyDisciplineId($physical);

        // A dual-licensed provider manages their own pivot. discipline_id still points at the
        // primary, so a naive "backfill from discipline_id" would be a no-op here — but a
        // careless sync() would silently drop the second discipline. Prove it doesn't.
        $provider->disciplines()->syncWithoutDetaching([$speech->id => ['is_primary' => false]]);

        $provider->update(['radius_max_miles' => 40]);

        $this->assertEqualsCanonicalizing(
            [$physical->id, $speech->id],
            $provider->fresh()->disciplines()->pluck('sp_disciplines.id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    public function test_the_backfill_does_not_recurse(): void
    {
        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Recursion Check']);

        $saves = 0;
        Provider::saved(function () use (&$saves): void {
            $saves++;
        });

        $this->saveProviderWithOnlyDisciplineId($discipline);

        // The pivot write must not re-fire Provider::saved. If syncWithoutDetaching ever
        // touched the parent row, this would climb (or hang).
        $this->assertSame(1, $saves);
    }
}
