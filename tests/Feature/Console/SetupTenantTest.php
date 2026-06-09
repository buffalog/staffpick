<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTest;

class SetupTenantTest extends FeatureTest
{
    private const EMAIL = 'jeremy@thepihls.org';

    private const SLUG = 'fcts';

    protected function setUp(): void
    {
        parent::setUp();

        // staffpick_test keeps the plain tenants_domain_unique index — the
        // filtered-index migration self-skips on the local dblib driver — so only
        // one null-domain tenant can exist at a time. Reset tenant state between
        // tests so each creates its own. Not needed on Railway's filtered index.
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
