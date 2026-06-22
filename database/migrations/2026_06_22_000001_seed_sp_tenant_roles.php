<?php

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the four StaffPick tenant roles (sp_admin, sp_staff, sp_provider,
 * sp_referrer) as tenant-scoped roles: is_tenant_role=true, tenant_id=null.
 * Idempotent via firstOrCreate, matching RolesAndPermissionsSeeder's pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (TenancyPermissionConstants::SP_TENANT_ROLES as $roleName) {
            Role::query()->firstOrCreate([
                'name' => $roleName,
                'is_tenant_role' => true,
            ], [
                'guard_name' => 'web',
            ]);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::query()
            ->where('is_tenant_role', true)
            ->whereIn('name', TenancyPermissionConstants::SP_TENANT_ROLES)
            ->delete();
    }
};
