<?php

namespace Tests\Feature\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\Providers\Pages\EditProvider;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ProviderEditingScopeTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function userWithSpRole(string $role): User
    {
        $user = $this->createUser($this->tenant);
        app(TenantPermissionService::class)->assignTenantUserRoles($this->tenant, $user, [$role]);

        return $user;
    }

    public function test_update_is_allowed_for_staff_hr_admin_and_denied_for_others(): void
    {
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([
            TenancyPermissionConstants::ROLE_SP_STAFF,
            TenancyPermissionConstants::ROLE_SP_HR,
            TenancyPermissionConstants::ROLE_SP_ADMIN,
        ] as $role) {
            $this->actingAs($this->userWithSpRole($role));
            $this->assertTrue(Gate::allows('update', $provider), "$role should be able to update a provider");
        }

        // A tenant member with no sp role cannot edit providers.
        $this->actingAs($this->createUser($this->tenant));
        $this->assertFalse(Gate::allows('update', $provider));
    }

    public function test_creating_and_deleting_providers_stays_admin_only(): void
    {
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $this->assertFalse(Gate::allows('create', Provider::class));
        $this->assertFalse(Gate::allows('delete', $provider));

        $this->actingAs($this->createTenantAdmin($this->tenant));
        $this->assertTrue(Gate::allows('create', Provider::class));
        $this->assertTrue(Gate::allows('delete', $provider));
    }

    public function test_tier_change_stamps_the_audit_fields(): void
    {
        $admin = $this->createTenantAdmin($this->tenant);
        $this->actingAs($admin);
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'tier_id' => null]);

        $provider->update(['tier_id' => 999]); // no FK on tier_id; the stamp is what we test

        $fresh = $provider->fresh();
        $this->assertSame($admin->id, (int) $fresh->tier_changed_by_user_id);
        $this->assertNotNull($fresh->tier_changed_at);
    }

    public function test_privileged_fields_are_hidden_from_staff_and_visible_to_admin(): void
    {
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        Livewire::test(EditProvider::class, ['record' => $provider->id])
            ->assertFormFieldHidden('tier_id')
            ->assertFormFieldHidden('payroll_id')
            ->assertFormFieldHidden('tax_id')
            ->assertFormFieldHidden('status');

        $this->actingAs($this->createTenantAdmin($this->tenant));
        Livewire::test(EditProvider::class, ['record' => $provider->id])
            ->assertFormFieldIsVisible('tier_id')
            ->assertFormFieldIsVisible('payroll_id')
            ->assertFormFieldIsVisible('status');
    }

    public function test_edit_save_guard_strips_privileged_fields_for_staff(): void
    {
        // A discipline is required by the form, so the provider needs one for save() to pass
        // validation and actually reach the guard (the factory attaches the pivot from
        // discipline_id).
        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy', 'abbreviation' => 'PT']);
        $provider = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $discipline->id,
            'tier_id' => null,
            'payroll_id' => null,
        ]);

        // A forged submit sets privileged keys directly in the Livewire form state; the
        // save-hook guard must drop them for sp_staff, leaving the stored values untouched.
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        Livewire::test(EditProvider::class, ['record' => $provider->id])
            ->set('data.tier_id', 999)
            ->set('data.payroll_id', 'HACK-1')
            ->call('save');

        $fresh = $provider->fresh();
        $this->assertNull($fresh->tier_id);
        $this->assertNull($fresh->payroll_id);
    }
}
