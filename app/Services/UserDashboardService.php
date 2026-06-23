<?php

namespace App\Services;

use App\Constants\TenancyPermissionConstants;
use App\Models\User;

class UserDashboardService
{
    public function getUserDashboardUrl(User $user): string
    {
        // Super admins land in the platform panel (they hold no tenant membership).
        if ($user->isSuperAdmin()) {
            return url('/superadmin');
        }

        $tenant = $user->tenants()->orderByPivot('is_default', 'desc')->first();

        if ($tenant !== null) {
            // Route to the panel matching the user's StaffPick role: admin/staff →
            // dashboard, provider → provider portal, referrer → referrer portal.
            // Users with no SP role keep the existing dashboard landing (they can
            // always access it; the referrer fallback in defaultSpPanel would 403).
            $panel = $user->hasAnySpRole($tenant->id, TenancyPermissionConstants::SP_TENANT_ROLES)
                ? $user->defaultSpPanel($tenant->id)
                : 'dashboard';

            return route("filament.{$panel}.pages.dashboard", ['tenant' => $tenant]);
        }

        // Signed in but no workspace yet — a clear dead-end rather than a bare redirect.
        return route('staffpick.no-workspace');
    }
}
