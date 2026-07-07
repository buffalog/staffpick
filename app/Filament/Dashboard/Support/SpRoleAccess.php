<?php

namespace App\Filament\Dashboard\Support;

use App\Constants\TenancyPermissionConstants;
use Filament\Facades\Filament;

class SpRoleAccess
{
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
