<?php

namespace Tests\Feature;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Tests\TestCase;

class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        // Schema (migrate:fresh) and the one-time seed are now owned by RefreshDatabase on
        // the base TestCase, which also wraps each test in a rolled-back transaction.
        parent::setUp();

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

        // Assign sp_admin alongside the legacy admin role. StaffPick pages/policies gate on
        // sp_admin/sp_staff via SpRoleAccess after the RBAC overhaul, so a test admin must
        // carry sp_admin to authorize the same way a real admin account does in production —
        // without it every canAccess() abort_unless(...) returns 403.
        app(TenantPermissionService::class)->assignTenantUserRoles(
            $tenant,
            $user,
            [
                TenancyPermissionConstants::ROLE_ADMIN,
                TenancyPermissionConstants::ROLE_SP_ADMIN,
            ],
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
