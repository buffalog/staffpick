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
}
