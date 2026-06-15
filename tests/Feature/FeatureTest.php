<?php

namespace Tests\Feature;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Database\Seeders\Testing\TestingDatabaseSeeder;
use Filament\Facades\Filament;
use Tests\TestCase;

class FeatureTest extends TestCase
{
    protected static bool $setUpHasRunOnce = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$setUpHasRunOnce) {
            $this->artisan('migrate:fresh');
            $this->seed(TestingDatabaseSeeder::class);

            static::$setUpHasRunOnce = true;
        }

        $this->configureDefaultCurrency();
        $this->withoutExceptionHandling();
        $this->withoutVite();
    }

    protected function createUser(?Tenant $tenant = null, array $tenantPermissions = [], array $attributes = [])
    {
        $user = User::factory()->create($attributes);

        if ($tenant !== null) {
            $tenant->users()->attach($user);

            foreach ($tenantPermissions as $permission) {
                $user->tenants()->where('tenant_id', $tenant->id)->first()->pivot->givePermissionTo($permission);
            }
        }

        return $user;
    }

    protected function createTenant()
    {
        return Tenant::factory()->create();
    }

    /**
     * Create a user who is an ADMIN of the given tenant. StaffPick PHI resources
     * (intake requests, subjects, providers, referral sources) are restricted to
     * tenant admins via StaffPickAdminPolicy, so tests exercising those resources
     * must act as an admin.
     */
    protected function createTenantAdmin(?Tenant $tenant = null, array $attributes = []): User
    {
        $tenant = $tenant ?? $this->createTenant();
        $user = $this->createUser($tenant, [], $attributes);

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        app(TenantPermissionService::class)->assignTenantUserRole(
            $tenant,
            $user,
            TenancyPermissionConstants::ROLE_ADMIN,
        );

        return $user;
    }

    protected function createAdminUser()
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $user->each(function ($user) {
            $user->assignRole('admin');
        });

        return $user;
    }

    protected function configureDefaultCurrency(): void
    {
        config()->set('app.default_currency', 'USD');
    }
}
