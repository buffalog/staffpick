<?php

namespace Tests\Feature\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Tests\Feature\FeatureTest;

class AssignerRoleScopeTest extends FeatureTest
{
    private const ASSIGNER_ROLES = [
        TenancyPermissionConstants::ROLE_SP_ADMIN,
        TenancyPermissionConstants::ROLE_SP_STAFF,
        TenancyPermissionConstants::ROLE_SP_HR,
    ];

    public function test_scope_includes_staff_admin_hr_and_excludes_provider_only_and_other_tenants(): void
    {
        $tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        $perms = app(TenantPermissionService::class);

        $staff = $this->createUser($tenant);
        $perms->assignTenantUserRoles($tenant, $staff, [TenancyPermissionConstants::ROLE_SP_STAFF]);

        $admin = $this->createUser($tenant);
        $perms->assignTenantUserRoles($tenant, $admin, [TenancyPermissionConstants::ROLE_SP_ADMIN]);

        $hr = $this->createUser($tenant);
        $perms->assignTenantUserRoles($tenant, $hr, [TenancyPermissionConstants::ROLE_SP_HR]);

        // The bug: a provider-only user was showing up as an assigner option.
        $provider = $this->createUser($tenant);
        $perms->assignTenantUserRoles($tenant, $provider, [TenancyPermissionConstants::ROLE_SP_PROVIDER]);

        // A staff user in a DIFFERENT tenant must not leak into this tenant's options.
        $otherTenant = $this->createTenant();
        $otherStaff = $this->createUser($otherTenant);
        $perms->assignTenantUserRoles($otherTenant, $otherStaff, [TenancyPermissionConstants::ROLE_SP_STAFF]);

        $ids = SpRoleAccess::usersHoldingAnySpRole(User::query(), $tenant->id, self::ASSIGNER_ROLES)
            ->pluck('id')
            ->all();

        $this->assertContains($staff->id, $ids);
        $this->assertContains($admin->id, $ids);
        $this->assertContains($hr->id, $ids);
        $this->assertNotContains($provider->id, $ids, 'A provider-only user must not be an assigner option.');
        $this->assertNotContains($otherStaff->id, $ids, 'A staff user from another tenant must not leak in.');
    }
}
