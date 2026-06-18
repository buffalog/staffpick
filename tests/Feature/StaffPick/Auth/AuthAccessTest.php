<?php

namespace Tests\Feature\StaffPick\Auth;

use App\Filament\Dashboard\Pages\CredentialingQueue;
use App\Filament\Dashboard\Pages\SchedulerBoard;
use App\Models\StaffPick\IntakeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserDashboardService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\FeatureTest;

class AuthAccessTest extends FeatureTest
{
    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->is_super_admin = true;
        $user->save();

        return $user->fresh();
    }

    // ---- Decision 1: no-workspace dead-end ----

    public function test_a_signed_in_user_with_no_workspace_lands_on_the_dead_end(): void
    {
        $user = User::factory()->create(); // not attached to any tenant

        $this->assertSame(
            route('staffpick.no-workspace'),
            app(UserDashboardService::class)->getUserDashboardUrl($user),
        );

        $this->actingAs($user)->get('/no-workspace')
            ->assertOk()
            ->assertSee('support@staffpick.dev')
            ->assertSee('belong to any workspace yet');
    }

    public function test_super_admin_dashboard_url_is_the_super_admin_panel(): void
    {
        $this->assertSame(url('/superadmin'), app(UserDashboardService::class)->getUserDashboardUrl($this->superAdmin()));
    }

    public function test_a_tenant_member_still_routes_to_their_dashboard(): void
    {
        $tenant = $this->createTenant();
        $member = $this->createTenantAdmin($tenant);

        $this->assertStringContainsString(
            '/dashboard/'.$tenant->uuid,
            app(UserDashboardService::class)->getUserDashboardUrl($member),
        );
    }

    // ---- Decision 2: super admin in-tenant access ----

    public function test_super_admin_can_access_admin_pages_in_any_tenant_via_bypass(): void
    {
        $tenant = $this->createTenant();
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        // Super admin is NOT a member of this tenant, yet both ROLE_ADMIN-gated pages open.
        $this->assertTrue(SchedulerBoard::canAccess());
        $this->assertTrue(CredentialingQueue::canAccess());
    }

    public function test_super_admin_passes_phi_resource_authorization(): void
    {
        $tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        $superAdmin = $this->superAdmin();
        $nonAdmin = $this->createUser($tenant);

        // Gate::before grants super admins the StaffPickAdminPolicy-gated PHI models…
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', IntakeRequest::class));
        // …while a normal non-admin member is still denied.
        $this->assertFalse(Gate::forUser($nonAdmin)->allows('viewAny', IntakeRequest::class));
    }

    public function test_a_non_admin_member_cannot_access_the_admin_pages(): void
    {
        $tenant = $this->createTenant();
        $this->actingAs($this->createUser($tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        $this->assertFalse(SchedulerBoard::canAccess());
        $this->assertFalse(CredentialingQueue::canAccess());
    }
}
