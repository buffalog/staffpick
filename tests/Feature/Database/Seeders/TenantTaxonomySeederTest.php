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
        $this->assertSame(4, ProviderTier::where('tenant_id', $tenant->id)->count());
        $this->assertSame(40, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
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
        $this->assertSame(2, $gold->priority);

        $license = CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'State License (PT)')->first();
        $this->assertNotNull($license);
        $this->assertTrue($license->is_required);
        $this->assertTrue($license->has_expiry);
        $this->assertTrue($license->deactivate_on_expiry);
        $this->assertSame(30, $license->expiry_warning_days);
        $this->assertTrue($license->visible_to_scheduler);

        $backgroundCheck = CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'Background Check')->first();
        $this->assertNotNull($backgroundCheck);
        $this->assertFalse($backgroundCheck->has_expiry);
    }

    public function test_credential_types_carry_scheduler_visibility_flags(): void
    {
        $tenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        // Clinical/licensing types are visible to the Scheduler view (sp_staff)...
        $this->assertTrue(
            (bool) CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'CPR/BLS')->value('visible_to_scheduler'),
        );
        // ...HR-only documents are not.
        $this->assertFalse(
            (bool) CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'Resume')->value('visible_to_scheduler'),
        );

        // Folded demo names are gone; the canonical labels are present.
        $this->assertSame(0, CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'CPR Certification')->where('is_active', true)->count());
        $this->assertSame(1, CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'Liability/Malpractice Insurance')->count());
    }

    public function test_reseed_preserves_admin_edited_credential_type_fields(): void
    {
        // The seeder runs on every deploy; per-tenant tuning done in the Credentialing
        // Policies UI must survive it (only new types get their defaults seeded).
        $tenant = $this->createTenant();
        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        CredentialDocumentType::where('tenant_id', $tenant->id)
            ->where('name', 'Resume')
            ->update(['visible_to_scheduler' => true, 'has_expiry' => true, 'is_active' => false]);

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $resume = CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'Resume')->first();
        $this->assertTrue((bool) $resume->visible_to_scheduler);
        $this->assertTrue((bool) $resume->has_expiry);
        $this->assertFalse((bool) $resume->is_active);
        $this->assertSame(40, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
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

        $this->assertSame(4, ProviderTier::where('tenant_id', $tenant->id)->count());
        $this->assertSame(40, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
        $this->assertSame(
            2,
            (int) ProviderTier::where('tenant_id', $tenant->id)->where('name', 'Gold')->value('priority'),
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
            (bool) CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'CPR/BLS')->value('is_required'),
        );
    }

    public function test_only_core_credential_types_are_required_by_default(): void
    {
        // seedForTenant() (real tenants via staffpick:setup-tenant) keeps only the
        // licensing essentials + Background Check + W-9 required; the broader HR/clinical
        // taxonomy is optional so onboarding isn't walled behind 40 documents.
        $tenant = $this->createTenant();

        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $required = CredentialDocumentType::where('tenant_id', $tenant->id)
            ->where('is_required', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $this->assertSame([
            'Background Check',
            'State License (OT)',
            'State License (PT)',
            'State License (SLP)',
            'W-9',
        ], $required);

        $this->assertFalse(
            (bool) CredentialDocumentType::where('tenant_id', $tenant->id)->where('name', 'Resume')->value('is_required'),
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
