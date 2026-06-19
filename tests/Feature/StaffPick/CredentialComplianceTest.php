<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Services\StaffPick\CredentialComplianceService;
use App\Services\StaffPick\SchedulerNotificationService;
use Tests\Feature\FeatureTest;

class CredentialComplianceTest extends FeatureTest
{
    private Tenant $tenant;

    private int $typeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        // FeatureTest shares the DB with no rollback, and the sweep scans every tenant —
        // clear credentials so each test's selection is deterministic.
        ProviderCredential::query()->delete();
    }

    private function type(bool $deactivate, int $warningDays = 30, bool $hasExpiry = true): CredentialDocumentType
    {
        return CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Compliance Type '.(++$this->typeSequence).' '.$this->name(),
            'verification_method' => 'manual',
            'has_expiry' => $hasExpiry,
            'expiry_warning_days' => $warningDays,
            'deactivate_on_expiry' => $deactivate,
        ]);
    }

    private function provider(bool $active = true): Provider
    {
        return Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => $active,
            'status' => $active ? Provider::STATUS_ACTIVE : Provider::STATUS_INACTIVE,
            'user_id' => null,
            // Bell-only: keeps notifyProvider() from reaching SMS/email in tests.
            'preferred_contact_channel' => Provider::CHANNEL_PORTAL,
        ]);
    }

    private function credential(Provider $provider, CredentialDocumentType $type, string $expiresAt): ProviderCredential
    {
        return ProviderCredential::create([
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_auto_deactivation_fires_for_expired_deactivate_on_expiry_credential(): void
    {
        $provider = $this->provider(active: true);
        $type = $this->type(deactivate: true);
        $this->credential($provider, $type, now()->subDays(3)->toDateString());

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldReceive('providerAutoDeactivated')->once();

        $count = app(CredentialComplianceService::class)->deactivateExpired();

        $this->assertSame(1, $count);

        $provider->refresh();
        $this->assertFalse($provider->is_active);
        $this->assertStringStartsWith(CredentialComplianceService::REASON_PREFIX, (string) $provider->deactivation_reason);
        $this->assertNotNull($provider->deactivated_at);
    }

    public function test_auto_deactivation_skips_types_with_deactivate_on_expiry_false(): void
    {
        $provider = $this->provider(active: true);
        $type = $this->type(deactivate: false);
        $this->credential($provider, $type, now()->subDays(3)->toDateString());

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldNotReceive('providerAutoDeactivated');

        $count = app(CredentialComplianceService::class)->deactivateExpired();

        $this->assertSame(0, $count);
        $this->assertTrue($provider->refresh()->is_active);
    }

    public function test_provider_reactivates_when_renewed_credential_is_valid(): void
    {
        $provider = $this->provider(active: false);
        $provider->update(['deactivation_reason' => CredentialComplianceService::REASON_PREFIX.' State License (PT) expired on Jan 1, 2026']);

        $type = $this->type(deactivate: true);
        // Renewed: now valid well into the future.
        $this->credential($provider, $type, now()->addYear()->toDateString());

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldReceive('providerReactivated')->once();

        $reactivated = app(CredentialComplianceService::class)->reactivateIfEligible($provider);

        $this->assertTrue($reactivated);

        $provider->refresh();
        $this->assertTrue($provider->is_active);
        $this->assertNull($provider->deactivation_reason);
        $this->assertNull($provider->deactivated_at);
    }

    public function test_provider_does_not_reactivate_while_a_deactivating_credential_is_still_expired(): void
    {
        $provider = $this->provider(active: false);
        $provider->update(['deactivation_reason' => CredentialComplianceService::REASON_PREFIX.' CPR Certification expired on Jan 1, 2026']);

        $type = $this->type(deactivate: true);
        $this->credential($provider, $type, now()->subDays(2)->toDateString()); // still expired

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldNotReceive('providerReactivated');

        $this->assertFalse(app(CredentialComplianceService::class)->reactivateIfEligible($provider));
        $this->assertFalse($provider->refresh()->is_active);
    }

    public function test_reactivation_ignores_providers_deactivated_for_other_reasons(): void
    {
        $provider = $this->provider(active: false);
        $provider->update(['deactivation_reason' => 'Manually deactivated by admin']);

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldNotReceive('providerReactivated');

        $this->assertFalse(app(CredentialComplianceService::class)->reactivateIfEligible($provider));
        $this->assertFalse($provider->refresh()->is_active);
    }

    public function test_warning_alerts_use_per_type_warning_days(): void
    {
        $provider = $this->provider(active: true);
        $type30 = $this->type(deactivate: false, warningDays: 30);
        $type60 = $this->type(deactivate: false, warningDays: 60);

        // 45 days out: outside the 30-day type's window, inside the 60-day type's window.
        $this->credential($provider, $type30, now()->addDays(45)->toDateString());
        $within60 = $this->credential($provider, $type60, now()->addDays(45)->toDateString());

        $mock = $this->mock(SchedulerNotificationService::class);
        $mock->shouldReceive('credentialExpiring')
            ->once()
            ->withArgs(fn (ProviderCredential $credential): bool => $credential->id === $within60->id);

        $this->artisan('staffpick:check-credential-expiry')->assertSuccessful();
    }
}
