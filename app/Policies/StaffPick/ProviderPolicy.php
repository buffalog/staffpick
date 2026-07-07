<?php

namespace App\Policies\StaffPick;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Provider;
use App\Models\User;

/**
 * Provider records are split off the shared StaffPickAdminPolicy so editing can open to
 * sp_staff and sp_hr (field-scoped in the form) while intake requests, subjects, and
 * referral sources stay admin-only on the shared policy.
 *
 * view/update: super-admin, sp_admin, sp_hr, sp_staff (SpRoleAccess::canEditProviders).
 * Creating and deleting providers stays admin-only. Super-admins also bypass via the
 * Gate::before hook in AuthServiceProvider, but the explicit checks keep the policy honest
 * on its own.
 */
class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return SpRoleAccess::canEditProviders();
    }

    public function view(User $user, Provider $provider): bool
    {
        return SpRoleAccess::canEditProviders();
    }

    public function create(User $user): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function update(User $user, Provider $provider): bool
    {
        return SpRoleAccess::canEditProviders();
    }

    public function delete(User $user, Provider $provider): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function restore(User $user, Provider $provider): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function forceDelete(User $user, Provider $provider): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public function reorder(User $user): bool
    {
        return SpRoleAccess::isAdmin();
    }
}
