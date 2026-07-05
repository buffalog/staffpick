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
