<?php

namespace App\Policies\StaffPick;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Write policy for the case/patient pipeline models (intake requests + subjects)
 * that schedulers own day-to-day. Extends StaffPickAdminPolicy but opens create
 * and update to sp_staff (schedulers) as well as sp_admin — creating a case and
 * filling in a subject's details (insurance, contacts, etc.) is core scheduler
 * work, not an admin-only task.
 *
 * Destructive ops (delete/restore/forceDelete/reorder) remain admin-only via the
 * inherited methods. Provider + ReferralSource stay on the base admin-only policy
 * so provider credentialing (gated on Provider 'update') is not exposed to staff.
 */
class StaffPickPipelinePolicy extends StaffPickAdminPolicy
{
    public function create(User $user): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public function update(User $user, Model $record): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }
}
