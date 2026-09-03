<?php

namespace Tests\Feature\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Widgets\StaffDashboardStats;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * The staff dashboard at /dashboard/{tenant} renders tenant-wide case data, including the
 * oldest pending case's subject surname. Every other page in that panel gates on an SP role;
 * the landing page did not, so a provider-only user saw staff widgets and a patient
 * identifier for a case they are not assigned to (minimum-necessary / HIPAA Policy 12).
 */
class ProviderDashboardAccessTest extends FeatureTest
{
    private function seedPendingCaseNamed(Tenant $tenant, string $surname): IntakeRequest
    {
        $discipline = Discipline::create([
            'tenant_id' => $tenant->id,
            'name' => 'Physical Therapy',
            'abbreviation' => 'PT',
            'is_active' => true,
        ]);

        $subject = Subject::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jeremy',
            'last_name' => $surname,
        ]);

        return IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);
    }

    private function userWithSpRoles(Tenant $tenant, array $roles): User
    {
        $user = $this->createUser($tenant);

        if ($roles !== []) {
            app(TenantPermissionService::class)->assignTenantUserRoles($tenant, $user, $roles);
        }

        return $user;
    }

    private function dashboardUrl(Tenant $tenant): string
    {
        return route('filament.dashboard.pages.dashboard', ['tenant' => $tenant->uuid]);
    }

    public function test_a_provider_only_user_is_sent_to_the_provider_portal_and_sees_no_patient_name(): void
    {
        $tenant = $this->createTenant();
        $this->seedPendingCaseNamed($tenant, 'Pihl');
        $provider = $this->userWithSpRoles($tenant, [TenancyPermissionConstants::ROLE_SP_PROVIDER]);

        $response = $this->actingAs($provider)->get($this->dashboardUrl($tenant));

        $response->assertRedirect(
            route('filament.provider.pages.dashboard', ['tenant' => $tenant->uuid])
        );
        $this->assertStringNotContainsString('Pihl', $response->getContent());
        $this->assertStringNotContainsString('Longest Pending', $response->getContent());
        $this->assertStringNotContainsString('Find Matches', $response->getContent());
    }

    public function test_a_referrer_only_user_is_sent_to_the_referrer_portal(): void
    {
        $tenant = $this->createTenant();
        $this->seedPendingCaseNamed($tenant, 'Pihl');
        $referrer = $this->userWithSpRoles($tenant, [TenancyPermissionConstants::ROLE_SP_REFERRER]);

        $this->actingAs($referrer)->get($this->dashboardUrl($tenant))->assertRedirect(
            route('filament.referrer.pages.dashboard', ['tenant' => $tenant->uuid])
        );
    }

    public function test_a_tenant_member_with_no_sp_role_is_sent_to_role_selection(): void
    {
        $tenant = $this->createTenant();
        $this->seedPendingCaseNamed($tenant, 'Pihl');
        $member = $this->userWithSpRoles($tenant, []);

        $this->actingAs($member)->get($this->dashboardUrl($tenant))->assertRedirect(
            route('filament.dashboard.pages.role-selection', ['tenant' => $tenant->uuid])
        );
    }

    /**
     * sp_hr is staff-side but not isAdminOrStaff, so this page stays closed to them. They do
     * hold the provider roster, which is where they land instead of a dead end.
     */
    public function test_an_hr_only_user_is_sent_to_the_provider_roster(): void
    {
        $tenant = $this->createTenant();
        $this->seedPendingCaseNamed($tenant, 'Pihl');
        $hr = $this->userWithSpRoles($tenant, [TenancyPermissionConstants::ROLE_SP_HR]);

        $response = $this->actingAs($hr)->get($this->dashboardUrl($tenant));

        $response->assertRedirect(
            ProviderResource::getUrl('index', [], true, 'dashboard', tenant: $tenant)
        );
        $this->assertStringNotContainsString('Pihl', $response->getContent());
    }

    public function test_staff_still_see_the_dashboard_with_the_pending_case(): void
    {
        $tenant = $this->createTenant();
        $this->seedPendingCaseNamed($tenant, 'Pihl');
        $staff = $this->userWithSpRoles($tenant, [TenancyPermissionConstants::ROLE_SP_STAFF]);

        $this->actingAs($staff)->get($this->dashboardUrl($tenant))
            ->assertSuccessful()
            ->assertSee('Longest Pending:')
            ->assertSee('Pihl');
    }

    /**
     * The stat widget is a Livewire component in its own right, so it needs its own gate —
     * Filament enforces a widget's canView() on hydrate, not on the page that embeds it.
     */
    public function test_the_stats_widget_is_gated_to_staff(): void
    {
        $tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant, isQuiet: true);

        $this->actingAs($this->userWithSpRoles($tenant, [TenancyPermissionConstants::ROLE_SP_PROVIDER]));
        $this->assertFalse(StaffDashboardStats::canView());

        $this->actingAs($this->userWithSpRoles($tenant, [TenancyPermissionConstants::ROLE_SP_STAFF]));
        $this->assertTrue(StaffDashboardStats::canView());
    }

    /**
     * sp_hr used to fall through defaultSpPanel()'s referrer default, and canAccessTenant()
     * 403s a non-referrer on that panel — so accepting an HR invitation landed on a dead end.
     *
     * @return array<string, array{0: array<int, string>, 1: string}>
     */
    public static function spPanelCases(): array
    {
        return [
            'admin' => [[TenancyPermissionConstants::ROLE_SP_ADMIN], 'dashboard'],
            'staff' => [[TenancyPermissionConstants::ROLE_SP_STAFF], 'dashboard'],
            'hr' => [[TenancyPermissionConstants::ROLE_SP_HR], 'dashboard'],
            'hr and provider' => [[TenancyPermissionConstants::ROLE_SP_HR, TenancyPermissionConstants::ROLE_SP_PROVIDER], 'dashboard'],
            'provider' => [[TenancyPermissionConstants::ROLE_SP_PROVIDER], 'provider'],
            'referrer' => [[TenancyPermissionConstants::ROLE_SP_REFERRER], 'referrer'],
            'no sp role' => [[], 'dashboard'],
        ];
    }

    /**
     * @param  array<int, string>  $roles
     */
    #[DataProvider('spPanelCases')]
    public function test_default_sp_panel_routes_each_role_to_a_panel_it_can_actually_reach(array $roles, string $expected): void
    {
        $tenant = $this->createTenant();
        $user = $this->userWithSpRoles($tenant, $roles);

        $this->assertSame($expected, $user->defaultSpPanel($tenant->id));

        // The panel must actually admit them, or the redirect is just a slower 403.
        Filament::setCurrentPanel(Filament::getPanel($expected));
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAs($user);

        $this->assertTrue($user->canAccessTenant($tenant), "{$expected} panel rejects the tenant for: ".implode(', ', $roles));
    }

    public function test_an_admin_still_sees_the_dashboard(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createTenantAdmin($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        $this->actingAs($admin)->get($this->dashboardUrl($tenant))->assertSuccessful();
    }
}
