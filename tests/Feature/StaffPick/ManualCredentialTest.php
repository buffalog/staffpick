<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Credentialing\ManualCredential;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use Tests\Feature\FeatureTest;

/**
 * Covers the type-first upload persistence + Other-promotion (spec sections 3-4):
 * selecting an existing type, promoting a novel "Other" name to a real type, and the
 * trim + case-insensitive collision check that reuses an existing type instead.
 */
class ManualCredentialTest extends FeatureTest
{
    private function makeType(Tenant $tenant, string $name, bool $hasExpiry = false, bool $visibleToScheduler = false): CredentialDocumentType
    {
        return CredentialDocumentType::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'is_required' => false,
            'has_expiry' => $hasExpiry,
            'expiry_warning_days' => $hasExpiry ? 30 : 0,
            'deactivate_on_expiry' => false,
            'is_active' => true,
            'visible_to_scheduler' => $visibleToScheduler,
            'verification_method' => CredentialDocumentType::METHOD_MANUAL,
        ]);
    }

    public function test_selecting_an_existing_type_creates_the_credential(): void
    {
        $tenant = $this->createTenant();
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id]);
        $type = $this->makeType($tenant, 'CPR/BLS', hasExpiry: true, visibleToScheduler: true);

        ManualCredential::create([
            'document_type_id' => (string) $type->id,
            'expires_at' => '2027-05-01',
        ], (int) $provider->id, (int) $tenant->id);

        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'verification_source' => ProviderCredential::SOURCE_MANUAL,
        ]);
        // No spurious type was created.
        $this->assertSame(1, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
    }

    public function test_other_promotes_a_new_credential_type(): void
    {
        $tenant = $this->createTenant();
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id]);

        ManualCredential::create([
            'document_type_id' => ManualCredential::OTHER,
            'custom_type_name' => '  Fingerprint Clearance  ',
            'expires_at' => '2027-01-01',
        ], (int) $provider->id, (int) $tenant->id);

        $type = CredentialDocumentType::where('tenant_id', $tenant->id)
            ->where('name', 'Fingerprint Clearance') // trimmed
            ->first();

        $this->assertNotNull($type);
        $this->assertTrue($type->is_active);
        $this->assertFalse($type->visible_to_scheduler); // safe HR-only default
        $this->assertTrue($type->has_expiry);            // a date was supplied
        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
        ]);
    }

    public function test_other_reuses_an_existing_type_case_insensitively(): void
    {
        $tenant = $this->createTenant();
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id]);
        $existing = $this->makeType($tenant, 'Rate Sheet');

        ManualCredential::create([
            'document_type_id' => ManualCredential::OTHER,
            'custom_type_name' => '  rate sheet ', // different case + whitespace
        ], (int) $provider->id, (int) $tenant->id);

        // No near-duplicate spawned — still one type, and the credential points at it.
        $this->assertSame(1, CredentialDocumentType::where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $existing->id,
        ]);
    }
}
