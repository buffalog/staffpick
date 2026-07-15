<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\TenantContext;
use Database\Seeders\StaffPick\DemoDataSeeder;
use Tests\Feature\FeatureTest;

class DemoDataSeederTest extends FeatureTest
{
    private function fctsTenant(): Tenant
    {
        $tenant = Tenant::firstOrCreate(['uuid' => 'fcts'], ['name' => 'First Class Therapy Solutions']);

        // Operate as this tenant for the rest of the test — the assertions read the seeded PHI
        // and run the engine, both of which are scoped. (The seeder's own run() restores to this.)
        app(TenantContext::class)->set($tenant);

        return $tenant;
    }

    public function test_it_seeds_providers_subjects_and_intake_requests(): void
    {
        $tenant = $this->fctsTenant();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(15, Provider::where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, Subject::where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, IntakeRequest::where('tenant_id', $tenant->id)->where('reference_number', 'like', 'DEMO-%')->count());

        // Variety the matching engine needs to exercise its branches.
        $this->assertGreaterThan(0, Provider::where('tenant_id', $tenant->id)->where('is_preferred', true)->count());
        $this->assertGreaterThan(0, Provider::where('tenant_id', $tenant->id)->whereNotNull('internal_rating')->count());
        $this->assertSame(1, Subject::where('tenant_id', $tenant->id)->where('language_preference', 'Spanish')->count());
        $this->assertSame(1, Subject::where('tenant_id', $tenant->id)->where('provider_gender_preference', 'female')->count());
    }

    public function test_each_demo_intake_resolves_matches(): void
    {
        $tenant = $this->fctsTenant();
        $this->seed(DemoDataSeeder::class);

        $engine = app(MatchingEngine::class);
        $intakes = IntakeRequest::where('tenant_id', $tenant->id)->where('reference_number', 'like', 'DEMO-%')->get();

        foreach ($intakes as $intake) {
            $this->assertGreaterThan(0, $engine->match($intake)->count(), "Intake {$intake->reference_number} returned no matches");
        }
    }

    public function test_it_is_idempotent(): void
    {
        $tenant = $this->fctsTenant();

        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(15, Provider::where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, Subject::where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, IntakeRequest::where('tenant_id', $tenant->id)->where('reference_number', 'like', 'DEMO-%')->count());
    }
}
