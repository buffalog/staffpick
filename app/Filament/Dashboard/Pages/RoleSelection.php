<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Sign-up bifurcation: a tenant member who has been granted no SP role yet picks
 * whether they're a clinician (provider) or a referring clinic (referrer). The
 * choice self-assigns the matching role and drops them into the right portal.
 * Admin/staff roles are never self-selectable here — those are granted via invite.
 */
class RoleSelection extends Page
{
    protected static ?string $slug = 'role-selection';

    protected string $view = 'filament.dashboard.pages.role-selection';

    public function getTitle(): string|Htmlable
    {
        return __('Welcome to StaffPick');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return false;
        }

        // Must be a member of this tenant...
        if (! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            return false;
        }

        // ...with no StaffPick role assigned yet.
        return $user->spRolesForTenant($tenant->id) === [];
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function becomeProvider()
    {
        return $this->selectRole(TenancyPermissionConstants::ROLE_SP_PROVIDER, 'filament.provider.pages.dashboard');
    }

    public function becomeReferrer()
    {
        return $this->selectRole(TenancyPermissionConstants::ROLE_SP_REFERRER, 'filament.referrer.pages.dashboard');
    }

    private function selectRole(string $role, string $routeName)
    {
        abort_unless(static::canAccess(), 403);

        $tenant = Filament::getTenant();
        $user = auth()->user();

        app(TenantPermissionService::class)->assignTenantUserRoles($tenant, $user, [$role]);

        return redirect()->route($routeName, ['tenant' => $tenant->uuid]);
    }
}
