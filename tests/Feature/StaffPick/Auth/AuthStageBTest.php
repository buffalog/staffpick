<?php

namespace Tests\Feature\StaffPick\Auth;

use App\Filament\Dashboard\Pages\SsoSettings;
use App\Filament\SuperAdmin\Pages\SystemHealth;
use App\Filament\SuperAdmin\Resources\SuperAdmins\Pages\ListSuperAdmins;
use App\Filament\SuperAdmin\Resources\Users\Pages\ListUsers;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class AuthStageBTest extends FeatureTest
{
    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->is_super_admin = true;
        $user->save();

        return $user->fresh();
    }

    private function ssoTenant(bool $required = false): Tenant
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'sso_provider' => 'google_workspace',
            'sso_client_id' => 'cid',
            'sso_client_secret' => 'csecret',
            'sso_domain' => 'fcts.com',
            'sso_enabled' => true,
            'sso_required' => $required,
        ]);

        return $tenant;
    }

    // ---- PART 5: super admin panel ----

    public function test_super_admin_can_reach_the_super_admin_panel(): void
    {
        // Panel denial for non-super-admins is unit-tested via canAccessPanel in
        // AuthFrameworkTest; here we confirm a super admin renders the panel home.
        $this->actingAs($this->superAdmin())
            ->get(SystemHealth::getUrl(panel: 'superadmin'))
            ->assertSuccessful();
    }

    public function test_super_admins_resource_lists_only_super_admins(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        $super = $this->superAdmin();
        $normal = User::factory()->create();

        Livewire::test(ListSuperAdmins::class)
            ->assertCanSeeTableRecords([$super])
            ->assertCanNotSeeTableRecords([$normal]);
    }

    public function test_super_admin_user_cannot_be_demoted_from_the_ui(): void
    {
        $actor = $this->superAdmin();
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        $target = $this->superAdmin();

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('toggleAdmin')->table($target));
    }

    public function test_reset_password_changes_a_users_password(): void
    {
        $actor = $this->superAdmin();
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        $target = User::factory()->create();
        $oldHash = $target->password;

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('resetPassword')->table($target))
            ->assertHasNoErrors();

        $this->assertNotSame($oldHash, $target->fresh()->password);
    }

    // ---- PART 4: tenant-aware login (escape hatch) ----

    public function test_login_shows_the_sso_button_for_an_sso_enabled_tenant(): void
    {
        $tenant = $this->ssoTenant();

        $this->withSession(['url.intended' => '/dashboard/'.$tenant->uuid.'/board'])
            ->get('/dashboard/login')
            ->assertOk()
            ->assertSee('Sign in with')          // SSO + Google buttons
            ->assertSee('authenticate', escape: false); // email/password form still present
    }

    public function test_login_keeps_the_password_escape_hatch_when_sso_is_required(): void
    {
        $tenant = $this->ssoTenant(required: true);

        $this->withSession(['url.intended' => '/dashboard/'.$tenant->uuid.'/board'])
            ->get('/dashboard/login')
            ->assertOk()
            // The escape-hatch reveal + the password form are both in the DOM.
            ->assertSee('Super admin? Sign in with email and password')
            ->assertSee('authenticate', escape: false);
    }

    public function test_login_without_sso_still_renders_the_standard_form(): void
    {
        $this->get('/dashboard/login')
            ->assertOk()
            ->assertSee('authenticate', escape: false);
    }

    // ---- PART 2: SSO settings page ----

    public function test_sso_settings_saves_and_encrypts_the_secret(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createTenantAdmin($tenant);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        Livewire::test(SsoSettings::class)
            ->fillForm([
                'sso_provider' => 'google_workspace',
                'sso_client_id' => 'client-123',
                'sso_client_secret' => 'shhh-secret',
                'sso_domain' => 'fcts.com',
                'sso_enabled' => true,
                'sso_required' => false,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $config = TenantConfig::where('tenant_id', $tenant->id)->first();
        $this->assertTrue($config->ssoEnabled());
        $this->assertSame('shhh-secret', $config->sso_client_secret);
        $this->assertNotSame('shhh-secret', DB::table('sp_tenant_configs')->where('tenant_id', $tenant->id)->value('sso_client_secret'));
    }
}
