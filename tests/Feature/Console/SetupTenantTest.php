<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\FeatureTest;

class SetupTenantTest extends FeatureTest
{
    private const EMAIL = 'jeremy@thepihls.org';

    private const SLUG = 'fcts';

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
}
