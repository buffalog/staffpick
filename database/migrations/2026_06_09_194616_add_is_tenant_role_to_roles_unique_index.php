<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The roles table stores two NULL-tenant "admin" rows that differ only by
 * is_tenant_role: a global admin-panel role (is_tenant_role = false) and a
 * tenant-role template (is_tenant_role = true, tenant_id = null) consumed by
 * TenantPermissionService. MySQL/Postgres allow both because they treat NULLs
 * in a unique index as distinct; SQL Server permits only one NULL per unique
 * index, so the second row collides on roles_tenant_name_guard_name_unique.
 *
 * Adding is_tenant_role to the unique index makes the two rows distinct on every
 * driver, restoring the intended design on SQL Server. The new index is strictly
 * more permissive than the old one, so existing rows cannot violate it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if ($this->hasIndex('roles_tenant_name_guard_name_unique')) {
                $table->dropUnique('roles_tenant_name_guard_name_unique');
            }

            $table->unique(
                ['tenant_id', 'name', 'guard_name', 'is_tenant_role'],
                'roles_tenant_name_guard_role_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if ($this->hasIndex('roles_tenant_name_guard_role_unique')) {
                $table->dropUnique('roles_tenant_name_guard_role_unique');
            }

            $table->unique(
                ['tenant_id', 'name', 'guard_name'],
                'roles_tenant_name_guard_name_unique',
            );
        });
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('roles') as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};
