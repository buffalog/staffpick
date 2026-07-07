<?php

namespace App\Filament\Dashboard\Support;

use App\Constants\TenancyPermissionConstants;
use App\Models\TenantUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SpRoleAccess
{
    /**
     * Constrain a User query to those holding ANY of the given SP roles within a tenant.
     *
     * The query-side counterpart to {@see User::hasAnySpRole()} — that method
     * is a runtime check on an already-loaded user, so anywhere a Builder constraint is
     * required (e.g. scoping a relationship Select's options) needs this instead. SP roles
     * hang off the tenant_user pivot via Spatie's model_has_roles table, so we EXISTS
     * against that pivot for the given tenant. This is what keeps provider-only users out
     * of staff-facing pickers like the intake Assigner.
     *
     * @param  Builder<User>  $query
     * @param  array<int, string>  $roles
     * @return Builder<User>
     */
    public static function usersHoldingAnySpRole(Builder $query, int|string|null $tenantId, array $roles): Builder
    {
        $model = $query->getModel();
        $userTable = $model->getTable();
        $userKey = $model->getKeyName();

        $rolesTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $tenantUserMorph = (new TenantUser)->getMorphClass();

        return $query->whereExists(
            fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('1')
                ->from('tenant_user')
                ->join($pivotTable, function ($join) use ($pivotTable, $morphKey, $tenantUserMorph): void {
                    $join->on("{$pivotTable}.{$morphKey}", '=', 'tenant_user.id')
                        ->where("{$pivotTable}.model_type", '=', $tenantUserMorph);
                })
                ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.{$roleKey}")
                ->whereColumn('tenant_user.user_id', "{$userTable}.{$userKey}")
                ->where('tenant_user.tenant_id', $tenantId)
                ->whereIn("{$rolesTable}.name", $roles),
        );
    }

    public static function isAdmin(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        if ($user->is_super_admin) {
            return true;
        }
        $tenant = Filament::getTenant();
        if ($tenant === null) {
            return false;
        }

        return $user->hasSpRole($tenant->id, TenancyPermissionConstants::ROLE_SP_ADMIN);
    }

    public static function isAdminOrStaff(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        if ($user->is_super_admin) {
            return true;
        }
        $tenant = Filament::getTenant();
        if ($tenant === null) {
            return false;
        }

        return $user->hasAnySpRole($tenant->id, [
            TenancyPermissionConstants::ROLE_SP_ADMIN,
            TenancyPermissionConstants::ROLE_SP_STAFF,
        ]);
    }

    /**
     * Full authority on the provider record: super-admin, tenant admin (sp_admin) and HR
     * (sp_hr). HR's authority intentionally matches admin here, not a middle tier. Gates the
     * privileged provider fields (payroll id, tax id, tier, active/deactivated status) that
     * sp_staff must not edit.
     */
    public static function isHrOrAdmin(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        if ($user->is_super_admin) {
            return true;
        }
        $tenant = Filament::getTenant();
        if ($tenant === null) {
            return false;
        }

        return $user->hasAnySpRole($tenant->id, [
            TenancyPermissionConstants::ROLE_SP_ADMIN,
            TenancyPermissionConstants::ROLE_SP_HR,
        ]);
    }

    /**
     * May edit provider records at all (the field-scoped grant): super-admin, sp_admin,
     * sp_hr, and sp_staff. Drives the ProviderResource access gate and ProviderPolicy update.
     * Which FIELDS each may edit is scoped separately by {@see isHrOrAdmin()}.
     */
    public static function canEditProviders(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        if ($user->is_super_admin) {
            return true;
        }
        $tenant = Filament::getTenant();
        if ($tenant === null) {
            return false;
        }

        return $user->hasAnySpRole($tenant->id, [
            TenancyPermissionConstants::ROLE_SP_ADMIN,
            TenancyPermissionConstants::ROLE_SP_STAFF,
            TenancyPermissionConstants::ROLE_SP_HR,
        ]);
    }

    /**
     * Whether the current user may see EVERY credential type regardless of the
     * visible_to_scheduler flag: super-admins, tenant admins (sp_admin) and HR (sp_hr).
     * Everyone else (notably sp_staff — the Scheduler view) is restricted to types
     * flagged visible_to_scheduler=true. Drives the ProviderCredential / CredentialDocumentType
     * visibility scopes.
     */
    public static function canSeeAllCredentials(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        if ($user->is_super_admin) {
            return true;
        }
        $tenant = Filament::getTenant();
        if ($tenant === null) {
            return false;
        }

        return $user->hasAnySpRole($tenant->id, [
            TenancyPermissionConstants::ROLE_SP_ADMIN,
            TenancyPermissionConstants::ROLE_SP_HR,
        ]);
    }
}
