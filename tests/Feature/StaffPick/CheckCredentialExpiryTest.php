<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Services\StaffPick\SchedulerNotificationService;
use Tests\Feature\FeatureTest;

class CheckCredentialExpiryTest extends FeatureTest
{
    private Tenant $tenant;

    private CredentialDocumentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->type = CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CPR Certification',
            'verification_method' => 'manual',
        ]);
    }

    private function credential(Provider $provider, ?string $expiresAt): ProviderCredential
    {
        return ProviderCredential::create([
            'provider_id' => $provider->id,
            'document_type_id' => $this->type->id,
            'status' => 'valid',
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_it_notifies_admins_only_for_active_providers_with_soon_expiring_credentials(): void
    {
        // FeatureTest shares the DB with no rollback, and the command queries all
        // tenants — clear credentials so the notify count is deterministic.
        ProviderCredential::query()->delete();
        $active = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'status' => 'active']);
        $inactive = Provider::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $expiringSoon = $this->credential($active, now()->addDays(10)->toDateString());   // included
        $this->credential($active, now()->addDays(200)->toDateString());                  // excluded: not soon
        $this->credential($inactive, now()->addDays(5)->toDateString());                  // excluded: provider inactive

        // Mock the notifier so the command's selection is asserted without the notifier
        // reading the date cast (which the local FreeTDS driver can't parse).
        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldReceive('credentialExpiring')
            ->once()
            ->withArgs(fn (ProviderCredential $credential): bool => $credential->id === $expiringSoon->id);

        $this->artisan('staffpick:check-credential-expiry')->assertSuccessful();
    }

    public function test_it_does_nothing_when_no_credentials_are_expiring(): void
    {
        ProviderCredential::query()->delete();
        $active = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'status' => 'active']);
        $this->credential($active, now()->addDays(300)->toDateString());

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldNotReceive('credentialExpiring');

        $this->artisan('staffpick:check-credential-expiry')->assertSuccessful();
    }
}
