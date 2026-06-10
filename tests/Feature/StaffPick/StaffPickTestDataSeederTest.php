<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Database\Seeders\StaffPickTestDataSeeder;
use Tests\Feature\FeatureTest;

class StaffPickTestDataSeederTest extends FeatureTest
{
    private function fctsTenant(): Tenant
    {
        return Tenant::firstOrCreate(['uuid' => 'fcts'], ['name' => 'First Class Therapy Solutions']);
    }

    public function test_it_seeds_fifteen_providers_and_five_subjects(): void
    {
        $tenant = $this->fctsTenant();

        $this->seed(StaffPickTestDataSeeder::class);

        $this->assertSame(15, Provider::where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, Subject::where('tenant_id', $tenant->id)->count());

        // Variety the matching engine needs to exercise its branches.
        $this->assertGreaterThan(0, Provider::where('tenant_id', $tenant->id)->where('is_preferred', true)->count());
        $this->assertGreaterThan(0, Provider::where('tenant_id', $tenant->id)->whereNotNull('internal_rating')->count());
        $this->assertSame(1, Subject::where('tenant_id', $tenant->id)->where('language_preference', 'Spanish')->count());
        $this->assertSame(1, Subject::where('tenant_id', $tenant->id)->where('provider_gender_preference', 'female')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $tenant = $this->fctsTenant();

        $this->seed(StaffPickTestDataSeeder::class);
        $this->seed(StaffPickTestDataSeeder::class);

        $this->assertSame(15, Provider::where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, Subject::where('tenant_id', $tenant->id)->count());
    }
}
