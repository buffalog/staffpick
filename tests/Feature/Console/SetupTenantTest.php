<?php

namespace Tests\Feature\Console;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTest;

class SetupTenantTest extends FeatureTest
{
    private const EMAIL = 'jeremy@thepihls.org';

    private const SLUG = 'fcts';

    protected function setUp(): void
    {
        parent::setUp();

        // The suite shares one database that is never rolled back, so reset tenant state
        // between tests and let each one create its own.
        DB::table('tenant_user')->delete();
        Tenant::query()->delete();
    }

    public function test_it_creates_the_admin_user_and_tenant(): void
    {
        $this->artisan('staffpick:setup-tenant', ['--password' => 'secret-pass-123'])
            ->assertSuccessful();

        $user = User::where('email', self::EMAIL)->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->is_admin);
        $this->assertTrue($user->hasRole('admin'));

        $tenant = Tenant::where('uuid', self::SLUG)->first();
        $this->assertNotNull($tenant);
        $this->assertSame('First Class Therapy Solutions', $tenant->name);

        // user is associated with the tenant
        $this->assertTrue($tenant->users()->where('users.id', $user->id)->exists());
    }

    /**
     * The provisioned admin must pass the gate every StaffPick page actually consults.
     *
     * This drives the COMMAND, not FeatureTest::createTenantAdmin(). The helper assigns
     * sp_admin directly, so it agreed with the app while the command did not, and every gate
     * test in the suite went through the helper. The command granted only the SaaSykit tenancy
     * 'admin' role, which SpRoleAccess never reads: the resulting admin reached the panel and
     * then failed isAdmin(), isAdminOrStaff(), canEditProviders() and canSeeAllCredentials(),
     * landing on a nav of Dashboard and Help. That is how the Postgres staging tenant was
     * provisioned on 2026-08-20, and sp_admin had to be granted by hand afterwards.
     */
    public function test_the_provisioned_admin_passes_the_staffpick_role_gates(): void
    {
        $this->artisan('staffpick:setup-tenant', ['--password' => 'secret-pass-123'])
            ->assertSuccessful();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $tenant = Tenant::where('uuid', self::SLUG)->firstOrFail();

        // Coverage guard 1: SpRoleAccess short-circuits to true for a super admin, which would
        // make every assertion below pass without a single tenant role being assigned. This
        // command only sets is_super_admin behind --super-admin-email, which is not passed here.
        $this->assertFalse((bool) $user->is_super_admin, 'A super admin bypasses every gate '.
            'asserted below, so this test would prove nothing.');

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAs($user);

        $this->assertTrue(SpRoleAccess::isAdminOrStaff(), 'The provisioned admin fails '.
            'isAdminOrStaff(), which gates Pending/Active/Discharged Cases, Subjects, the '.
            'Scheduler Board, the Service Calendar and the Credentialing Queue.');
        $this->assertTrue(SpRoleAccess::isAdmin(), 'The provisioned admin fails isAdmin(), '.
            'which gates Users, Invitations, Slack/SSO settings and the audit log.');
        $this->assertTrue(SpRoleAccess::canEditProviders());
        $this->assertTrue(SpRoleAccess::canSeeAllCredentials());

        // Coverage guard 2: prove the gate is discriminating in this context rather than
        // returning true for anyone. Without this, a misconfigured panel/tenant could make the
        // assertions above pass for the wrong reason.
        $this->actingAs($this->createUser($tenant));
        $this->assertFalse(SpRoleAccess::isAdminOrStaff(), 'A tenant member with no SP role '.
            'passes isAdminOrStaff(), so the gate is not discriminating and the assertions '.
            'above prove nothing.');
    }

    /**
     * Both roles, not one: the tenancy role carries the tenancy:* permissions and sp_admin
     * carries the StaffPick gates. assignTenantUserRoles() replaces the pivot's roles, so a
     * regression to two singular calls would silently leave only the last one.
     */
    public function test_it_grants_both_the_tenancy_admin_and_sp_admin_roles(): void
    {
        $this->artisan('staffpick:setup-tenant')->assertSuccessful();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $tenant = Tenant::where('uuid', self::SLUG)->firstOrFail();

        $roles = $user->spRolesForTenant($tenant->id);

        $this->assertContains(TenancyPermissionConstants::ROLE_SP_ADMIN, $roles);
        $this->assertContains(TenancyPermissionConstants::TENANT_CREATOR_ROLE, $roles);
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('staffpick:setup-tenant')->assertSuccessful();
        $this->artisan('staffpick:setup-tenant')->assertSuccessful();

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
        $this->assertSame(1, Tenant::where('uuid', self::SLUG)->count());

        $tenant = Tenant::where('uuid', self::SLUG)->first();
        $this->assertSame(1, $tenant->users()->where('email', self::EMAIL)->count());
    }

    public function test_it_outputs_the_login_url(): void
    {
        $this->artisan('staffpick:setup-tenant')
            ->expectsOutputToContain(route('login'))
            ->assertSuccessful();
    }

    public function test_it_accepts_a_custom_email_name_and_slug(): void
    {
        $this->artisan('staffpick:setup-tenant', [
            '--email' => 'test@staffpick.dev',
            '--name' => 'Test Agency Two',
            '--slug' => 'agency-two',
        ])->assertSuccessful();

        $user = User::where('email', 'test@staffpick.dev')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));

        $tenant = Tenant::where('uuid', 'agency-two')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('Test Agency Two', $tenant->name);
        $this->assertTrue($tenant->users()->where('users.id', $user->id)->exists());

        // the custom tenant is its own thing, not the default bootstrap admin
        $this->assertFalse($tenant->users()->where('email', self::EMAIL)->exists());
    }

    public function test_it_derives_the_slug_from_the_name_when_omitted(): void
    {
        $this->artisan('staffpick:setup-tenant', [
            '--email' => 'derive@staffpick.dev',
            '--name' => 'Test Agency Two',
        ])->assertSuccessful();

        // Str::slug('Test Agency Two') === 'test-agency-two'
        $this->assertSame(1, Tenant::where('uuid', 'test-agency-two')->count());
    }
}
