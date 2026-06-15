<?php

namespace App\Policies\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Restricts StaffPick PHI resources (intake requests, subjects, providers,
 * referral sources) to tenant administrators. These tables hold protected health
 * information; without a policy Filament allows every panel user full CRUD +
 * bulk delete, so a low-privilege tenant member could read/edit/delete all
 * patient data. One policy serves all four models — the rule is identical.
 *
 * Authorization is evaluated against the CURRENT Filament tenant. Outside a
 * Filament panel (console, queue, public token endpoints) there is no tenant and
 * this returns false; those paths never call Gate authorization on these models
 * (services write via the model directly), so the public/self-service flows are
 * unaffected.
 */
class StaffPickAdminPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function reorder(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    private function isTenantAdmin(User $user): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return in_array(
            TenancyPermissionConstants::ROLE_ADMIN,
            app(TenantPermissionService::class)->getTenantUserRoles($tenant, $user),
            true,
        );
    }
}
