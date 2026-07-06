<?php

namespace Tests\Feature\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Pages\CredentialingQueue;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
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

    private function createUserWithSpRole(string $role): User
    {
        $user = $this->createUser($this->tenant);

        app(TenantPermissionService::class)->assignTenantUserRoles($this->tenant, $user, [$role]);

        return $user;
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

    public function test_sp_staff_can_verify_a_scheduler_visible_credential(): void
    {
        // The reported bug: a scheduler was 403'd verifying a PT/OT/PTA state license,
        // which is flagged visible_to_scheduler. sp_staff must now be able to verify it.
        $this->actingAs($this->createUserWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $credential = $this->credential('manual', ['name' => 'State License (PT)', 'visible_to_scheduler' => true]);

        Livewire::test(CredentialingQueue::class)
            ->callAction(TestAction::make('verifyCredential')->table($credential), data: [
                'result' => ProviderCredential::VERIFICATION_VERIFIED,
                'notes' => 'Confirmed on the board.',
            ])
            ->assertHasNoErrors();

        $this->assertSame(ProviderCredential::VERIFICATION_VERIFIED, $credential->fresh()->verification_status);
    }

    public function test_sp_staff_cannot_verify_an_hr_only_credential(): void
    {
        $this->actingAs($this->createUserWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $credential = $this->credential('manual', ['name' => "Driver's License", 'visible_to_scheduler' => false]);

        $this->assertFalse($credential->isVerifiableByCurrentUser());
    }

    public function test_sp_hr_can_verify_an_hr_only_credential(): void
    {
        $this->actingAs($this->createUserWithSpRole(TenancyPermissionConstants::ROLE_SP_HR));
        $credential = $this->credential('manual', ['name' => "Driver's License", 'visible_to_scheduler' => false]);

        $this->assertTrue($credential->isVerifiableByCurrentUser());
    }
}
