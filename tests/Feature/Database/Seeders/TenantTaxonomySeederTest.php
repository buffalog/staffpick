<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\StaffPick\CancellationReason;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\OnHoldReason;
use App\Models\StaffPick\ProviderTier;
use App\Models\Tenant;
use Database\Seeders\TenantTaxonomySeeder;
use Tests\Feature\FeatureTest;

class TenantTaxonomySeederTest extends FeatureTest
{
    public function test_seeds_full_default_taxonomy_for_a_tenant(): void
    {
        $tenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $this->assertSame(3, Discipline::where('tenant_id', $tenant->id)->count());
        $this->assertSame(3, ProviderTier::where('tenant_id', $tenant->id)->count());
        $this->assertSame(7, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
        $this->assertSame(4, OnHoldReason::where('tenant_id', $tenant->id)->count());
        $this->assertSame(4, CancellationReason::where('tenant_id', $tenant->id)->count());
        $this->assertSame(4, DeclineReason::where('tenant_id', $tenant->id)->count());
    }

    public function test_taxonomy_rows_carry_the_expected_attributes(): void
    {
        $tenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $pt = Discipline::where('tenant_id', $tenant->id)->where('name', 'Physical Therapy')->first();
        $this->assertNotNull($pt);
        $this->assertSame('PT', $pt->abbreviation);
        $this->assertSame(1, $pt->sort_order);
        $this->assertTrue($pt->is_active);

        $gold = ProviderTier::where('tenant_id', $tenant->id)->where('name', 'Gold')->first();
        $this->assertNotNull($gold);
        $this->assertSame(1, $gold->priority);

        $license = CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'State License (PT)')->first();
        $this->assertNotNull($license);
        $this->assertTrue($license->is_required);
        $this->assertTrue($license->has_expiry);
        $this->assertTrue($license->deactivate_on_expiry);
        $this->assertSame(30, $license->expiry_warning_days);

        $backgroundCheck = CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'Background Check')->first();
        $this->assertNotNull($backgroundCheck);
        $this->assertFalse($backgroundCheck->has_expiry);
    }

    public function test_seeding_is_idempotent_and_refreshes_attributes(): void
    {
        $tenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        // Drift a row, then re-seed: counts must hold and the attribute is restored.
        ProviderTier::where('tenant_id', $tenant->id)
            ->where('name', 'Gold')
            ->update(['priority' => 99]);

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $this->assertSame(3, ProviderTier::where('tenant_id', $tenant->id)->count());
        $this->assertSame(7, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
        $this->assertSame(
            1,
            ProviderTier::where('tenant_id', $tenant->id)->where('name', 'Gold')->value('priority'),
        );
    }

    public function test_run_relaxes_credential_requirements_for_the_fcts_demo_tenant(): void
    {
        // run() targets the default 'fcts' tenant specifically.
        $tenant = Tenant::factory()->create(['uuid' => 'fcts']);

        app(TenantTaxonomySeeder::class)->run();

        // Only State License (PT) remains required for the demo tenant.
        $required = CredentialDocumentType::where('tenant_id', $tenant->id)
            ->where('is_required', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();
        $this->assertSame(['State License (PT)'], $required);

        // The other document types still exist — they're just no longer required to submit.
        $this->assertGreaterThan(1, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
        $this->assertFalse(
            (bool) CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'CPR Certification')->value('is_required'),
        );
    }

    public function test_seed_for_tenant_keeps_the_global_all_required_default(): void
    {
        // The shared seedForTenant() path (used by staffpick:setup-tenant for real
        // tenants) must NOT be relaxed — every credential stays required.
        $tenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $this->assertSame(
            0,
            CredentialDocumentType::where('tenant_id', $tenant->id)->where('is_required', false)->count(),
        );
    }

    public function test_taxonomy_is_scoped_to_the_seeded_tenant(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $this->assertSame(3, Discipline::where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, Discipline::where('tenant_id', $otherTenant->id)->count());
    }
}
