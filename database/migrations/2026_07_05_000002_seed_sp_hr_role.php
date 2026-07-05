<?php

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the sp_hr tenant role (is_tenant_role=true, tenant_id=null), added to
 * SP_TENANT_ROLES after the original four were seeded in 2026_06_22_000001. HR sees
 * every credential type regardless of visible_to_scheduler. Idempotent via firstOrCreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::query()->firstOrCreate([
            'name' => TenancyPermissionConstants::ROLE_SP_HR,
            'is_tenant_role' => true,
        ], [
            'guard_name' => 'web',
        ]);
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::query()
            ->where('is_tenant_role', true)
            ->where('name', TenancyPermissionConstants::ROLE_SP_HR)
            ->delete();
    }
};
