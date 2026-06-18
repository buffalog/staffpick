<?php

namespace Tests\Feature\StaffPick\Auth;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\AuthLog;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffPick\Auth\AuthLogger;
use App\Services\StaffPick\Auth\GoogleWorkspaceSsoProvider;
use App\Services\StaffPick\Auth\SsoConfigValidator;
use App\Services\StaffPick\Auth\SsoException;
use App\Services\StaffPick\Auth\SsoProviderInterface;
use App\Services\StaffPick\Auth\SsoService;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\FeatureTest;

class AuthFrameworkTest extends FeatureTest
{
    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->is_super_admin = true;
        $user->save();

        return $user->fresh();
    }

    // ---- PART 1: super admin ----

    public function test_super_admin_bypasses_tenant_membership(): void
    {
        $tenant = $this->createTenant();
        $superAdmin = $this->superAdmin();
        $normalUser = User::factory()->create();

        $this->assertTrue($superAdmin->canAccessTenant($tenant), 'super admin should bypass membership');
        $this->assertFalse($normalUser->canAccessTenant($tenant), 'non-member should be denied');
    }

    public function test_super_admin_sees_all_tenants_in_the_switcher(): void
    {
        $this->createTenant();
        $this->createTenant();

        $panel = Filament::getPanel('dashboard');

        $this->assertSame(Tenant::count(), $this->superAdmin()->getTenants($panel)->count());
    }

    public function test_only_super_admins_can_access_the_superadmin_panel(): void
    {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('superadmin');

        $this->assertTrue($this->superAdmin()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create()->canAccessPanel($panel));
    }

    public function test_is_super_admin_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'is_super_admin' => true,
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->isSuperAdmin(), 'is_super_admin must never be mass-assignable');
    }

    public function test_create_super_admin_command_creates_then_promotes_idempotently(): void
    {
        $this->artisan('staffpick:create-super-admin', ['--email' => 'root@fcts.example', '--password' => 'pw-1234567890'])
            ->assertSuccessful();

        $user = User::where('email', 'root@fcts.example')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue((bool) $user->is_admin);

        // Re-running promotes the same user (no duplicate).
        $this->artisan('staffpick:create-super-admin', ['--email' => 'root@fcts.example'])->assertSuccessful();
        $this->assertSame(1, User::where('email', 'root@fcts.example')->count());
    }

    public function test_setup_tenant_super_admin_email_associates_without_membership(): void
    {
        $tenant = Tenant::factory()->create(['uuid' => 'authtest']);

        $this->artisan('staffpick:setup-tenant', [
            '--slug' => 'authtest',
            '--name' => 'Auth Test Co',
            '--super-admin-email' => 'platform@fcts.example',
        ])->assertSuccessful();

        $super = User::where('email', 'platform@fcts.example')->first();
        $this->assertNotNull($super);
        $this->assertTrue($super->isSuperAdmin());

        // Associated via bypass — NOT a member of the tenant.
        $resolved = Tenant::where('uuid', 'authtest')->first();
        $this->assertFalse($resolved->users()->whereKey($super->id)->exists());
    }

    // ---- PART 2: SSO ----

    private function googleProvider(Tenant $tenant, array $configAttrs = []): GoogleWorkspaceSsoProvider
    {
        $config = TenantConfig::create(array_merge([
            'tenant_id' => $tenant->id,
            'sso_provider' => 'google_workspace',
            'sso_client_id' => 'cid',
            'sso_client_secret' => 'csecret',
            'sso_domain' => 'fcts.com',
            'sso_enabled' => true,
        ], $configAttrs));

        return new GoogleWorkspaceSsoProvider($tenant, $config, app(TenantPermissionService::class));
    }

    public function test_sso_validates_the_email_domain(): void
    {
        $tenant = $this->createTenant();
        $provider = $this->googleProvider($tenant);

        $this->assertTrue($provider->validateEmailDomain('jane@fcts.com'));
        $this->assertTrue($provider->validateEmailDomain('JANE@FCTS.COM'));
        $this->assertFalse($provider->validateEmailDomain('jane@gmail.com'));
    }

    public function test_sso_rejects_a_user_whose_domain_does_not_match(): void
    {
        $tenant = $this->createTenant();
        $provider = $this->googleProvider($tenant);

        $this->expectException(SsoException::class);
        $provider->resolveUser('attacker@gmail.com', 'Mallory', 'g-1');
    }

    public function test_sso_creates_a_new_user_and_the_first_member_becomes_admin(): void
    {
        $tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        $user = $this->googleProvider($tenant)->resolveUser('first@fcts.com', 'First Person', 'g-100');

        $this->assertSame('first@fcts.com', $user->email);
        $this->assertSame('g-100', $user->google_id);
        $this->assertTrue($tenant->users()->whereKey($user->id)->exists());
        $this->assertContains(
            TenancyPermissionConstants::ROLE_ADMIN,
            app(TenantPermissionService::class)->getTenantUserRoles($tenant, $user),
        );
    }

    public function test_sso_finds_an_existing_user_by_email(): void
    {
        $tenant = $this->createTenant();
        $existing = User::factory()->create(['email' => 'existing@fcts.com']);

        $user = $this->googleProvider($tenant)->resolveUser('existing@fcts.com', 'Existing', 'g-200');

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('g-200', $user->fresh()->google_id);
    }

    public function test_sso_service_returns_a_provider_only_when_enabled(): void
    {
        $tenant = $this->createTenant();
        $config = TenantConfig::create([
            'tenant_id' => $tenant->id,
            'sso_provider' => 'google_workspace',
            'sso_domain' => 'fcts.com',
            'sso_enabled' => false,
        ]);

        $service = app(SsoService::class);
        $this->assertNull($service->getSsoProvider($tenant), 'disabled SSO must return null');

        $config->update(['sso_enabled' => true]);
        $this->assertInstanceOf(GoogleWorkspaceSsoProvider::class, $service->getSsoProvider($tenant->fresh()));
    }

    public function test_sso_config_validator_reports_missing_fields(): void
    {
        $tenant = $this->createTenant();
        $existing = TenantConfig::create(['tenant_id' => $tenant->id]);

        $result = app(SsoConfigValidator::class)->check($tenant, $existing, ['sso_provider' => 'google_workspace']);

        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('client ID', $result->body);
    }

    public function test_sso_config_validator_warns_on_an_unimplemented_provider(): void
    {
        $tenant = $this->createTenant();
        $existing = TenantConfig::create(['tenant_id' => $tenant->id]);

        $result = app(SsoConfigValidator::class)->check($tenant, $existing, [
            'sso_provider' => 'okta',
            'sso_client_id' => 'cid',
            'sso_client_secret' => 'csecret',
            'sso_domain' => 'fcts.com',
        ]);

        $this->assertSame('warning', $result->status);
    }

    public function test_sso_config_validator_accepts_a_complete_google_config(): void
    {
        $tenant = $this->createTenant();
        $existing = TenantConfig::create(['tenant_id' => $tenant->id]);

        $result = app(SsoConfigValidator::class)->check($tenant, $existing, [
            'sso_provider' => 'google_workspace',
            'sso_client_id' => 'cid',
            'sso_client_secret' => 'csecret',
            'sso_domain' => 'fcts.com',
        ]);

        $this->assertSame('success', $result->status);
    }

    public function test_sso_client_secret_is_encrypted_at_rest(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'sso_provider' => 'google_workspace',
            'sso_client_secret' => 'top-secret-value',
        ]);

        // The model decrypts transparently…
        $this->assertSame('top-secret-value', TenantConfig::where('tenant_id', $tenant->id)->first()->sso_client_secret);
        // …but the raw column is ciphertext.
        $raw = DB::table('sp_tenant_configs')->where('tenant_id', $tenant->id)->value('sso_client_secret');
        $this->assertNotSame('top-secret-value', $raw);
        $this->assertNotEmpty($raw);
    }

    // ---- Security: audit logging ----

    public function test_auth_logger_records_success_and_failure(): void
    {
        AuthLog::query()->delete();

        app(AuthLogger::class)->success(AuthLog::EVENT_SUPER_ADMIN_LOGIN, ['email' => 'root@fcts.example', 'user_id' => 1]);
        app(AuthLogger::class)->failure(AuthLog::EVENT_SSO_CALLBACK, 'bad domain', ['email' => 'x@gmail.com']);

        $this->assertSame(1, AuthLog::where('event_type', AuthLog::EVENT_SUPER_ADMIN_LOGIN)->where('success', true)->count());
        $this->assertSame(1, AuthLog::where('event_type', AuthLog::EVENT_SSO_CALLBACK)->where('success', false)->where('error_message', 'bad domain')->count());
    }

    public function test_sso_callback_logs_the_user_in_and_audits_the_event(): void
    {
        AuthLog::query()->delete();
        $tenant = Tenant::factory()->create(['uuid' => 'ssocb']);
        $user = User::factory()->create(['email' => 'sso@fcts.com']);

        // Stub the SSO layer so we test the controller wiring without a live OAuth round-trip.
        $provider = Mockery::mock(SsoProviderInterface::class);
        $provider->shouldReceive('handleCallback')->andReturn($user);
        $service = Mockery::mock(SsoService::class);
        $service->shouldReceive('getSsoProvider')->andReturn($provider);
        $this->app->instance(SsoService::class, $service);

        $this->get('/auth/sso/ssocb/callback?code=abc&state=xyz')
            ->assertRedirect('/dashboard/ssocb');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, AuthLog::where('event_type', AuthLog::EVENT_SSO_CALLBACK)->where('success', true)->where('user_id', $user->id)->count());
    }
}
