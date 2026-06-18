<?php

namespace App\Services;

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
            return route('filament.dashboard.pages.dashboard', ['tenant' => $tenant]);
        }

        // Signed in but no workspace yet — a clear dead-end rather than a bare redirect.
        return route('staffpick.no-workspace');
    }
}
