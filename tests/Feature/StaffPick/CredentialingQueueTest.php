<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\CredentialingQueue;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class CredentialingQueueTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function credential(string $method, array $typeAttrs = [], array $credAttrs = []): ProviderCredential
    {
        $type = CredentialDocumentType::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'State License',
            'verification_method' => $method,
        ], $typeAttrs));

        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'state' => 'FL', 'first_name' => 'Pat', 'last_name' => 'Crewe']);

        // Unverified, no expires_at — appears in the queue and avoids the local date-read quirk.
        return ProviderCredential::create(array_merge([
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
        ], $credAttrs));
    }

    public function test_a_tenant_admin_sees_credentials_needing_attention(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $credential = $this->credential('manual', ['name' => 'CPR Certification']);

        Livewire::test(CredentialingQueue::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$credential])
            ->assertSee('Pat Crewe');
    }

    public function test_a_non_admin_cannot_access(): void
    {
        $this->actingAs($this->createUser($this->tenant));

        $this->assertFalse(CredentialingQueue::canAccess());
    }

    public function test_verify_now_runs_the_api_check_and_updates_status(): void
    {
        Http::fake(['physical-therapy-license-verification.p.rapidapi.com/*' => Http::response(['status' => 'Clear/Active'], 200)]);
        config()->set('services.rapidapi.key', 'test-key');
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $credential = $this->credential(
            'api',
            ['name' => 'State License (PT)', 'rapidapi_host' => 'physical-therapy-license-verification.p.rapidapi.com'],
            ['license_number' => 'PT12345'],
        );

        Livewire::test(CredentialingQueue::class)
            ->callAction(TestAction::make('verifyCredential')->table($credential))
            ->assertHasNoErrors();

        $this->assertSame(ProviderCredential::VERIFICATION_VERIFIED, $credential->fresh()->verification_status);
    }

    public function test_verify_now_deep_link_marks_pending_manual_confirmation(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $credential = $this->credential(
            'deep_link',
            ['name' => 'State License (OT)', 'deep_link_url_template' => 'https://mqa.example/x?LicenseNumber={license_number}&BoardCode=OT'],
            ['license_number' => 'OT-9'],
        );

        Livewire::test(CredentialingQueue::class)
            ->callAction(TestAction::make('verifyCredential')->table($credential))
            ->assertHasNoErrors();

        $this->assertSame(ProviderCredential::VERIFICATION_PENDING_MANUAL, $credential->fresh()->verification_status);
    }

    public function test_verify_now_manual_marks_verified_with_the_actor(): void
    {
        $admin = $this->createTenantAdmin($this->tenant);
        $this->actingAs($admin);
        $credential = $this->credential('manual', ['name' => 'CPR Certification']);

        Livewire::test(CredentialingQueue::class)
            ->callAction(TestAction::make('verifyCredential')->table($credential), data: [
                'result' => ProviderCredential::VERIFICATION_VERIFIED,
                'notes' => 'Checked the card.',
            ])
            ->assertHasNoErrors();

        $fresh = $credential->fresh();
        $this->assertSame(ProviderCredential::VERIFICATION_VERIFIED, $fresh->verification_status);
        $this->assertSame(ProviderCredential::SOURCE_MANUAL, $fresh->verification_source);
        $this->assertSame($admin->id, (int) $fresh->verified_by_user_id);
    }
}
