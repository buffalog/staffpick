<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\AuditEvents\AuditEventResource;
use App\Filament\Dashboard\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Models\StaffPick\AuditEvent;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class AuditEventResourceTest extends FeatureTest
{
    private function actAs(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);
    }

    private function staffUser(Tenant $tenant): User
    {
        $user = $this->createUser($tenant);
        app(TenantPermissionService::class)->assignTenantUserRoles($tenant, $user, [TenancyPermissionConstants::ROLE_SP_STAFF]);

        return $user;
    }

    private function auditRow(Tenant $tenant, array $overrides = []): AuditEvent
    {
        return AuditEvent::create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'actor_label' => 'sentinel-'.Str::random(8),
            'action' => 'viewed',
            'auditable_type' => Subject::class,
            'auditable_id' => 1,
            'subject_id' => 1,
            'occurred_at' => now(),
        ], $overrides));
    }

    public function test_an_admin_can_view_the_audit_list(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createTenantAdmin($tenant);
        $this->actAs($admin, $tenant);

        $this->assertTrue(AuditEventResource::canAccess());

        $this->get(AuditEventResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertSuccessful();
    }

    public function test_a_scheduler_is_denied(): void
    {
        $this->withExceptionHandling();

        $tenant = $this->createTenant();
        $staff = $this->staffUser($tenant);
        $this->actAs($staff, $tenant);

        $this->assertFalse(AuditEventResource::canAccess());

        $this->get(AuditEventResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertForbidden();
    }

    public function test_the_query_is_scoped_to_the_current_tenant(): void
    {
        $tenantA = $this->createTenant();
        $adminA = $this->createTenantAdmin($tenantA);
        $tenantB = $this->createTenant();

        $rowA = $this->auditRow($tenantA, ['actor_label' => 'scope-A-'.Str::random(6)]);
        $rowB = $this->auditRow($tenantB, ['actor_label' => 'scope-B-'.Str::random(6)]);

        $this->actAs($adminA, $tenantA);

        $labels = AuditEventResource::getEloquentQuery()
            ->whereIn('actor_label', [$rowA->actor_label, $rowB->actor_label])
            ->pluck('actor_label')
            ->all();

        $this->assertContains($rowA->actor_label, $labels);
        $this->assertNotContains($rowB->actor_label, $labels, 'tenant A must not see tenant B rows');
    }

    public function test_the_subject_filter_returns_only_that_patients_events(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createTenantAdmin($tenant);
        $this->actAs($admin, $tenant);

        $forPatient = $this->auditRow($tenant, ['subject_id' => 70001, 'actor_label' => 'pat-70001-'.Str::random(6)]);
        $otherPatient = $this->auditRow($tenant, ['subject_id' => 70002, 'actor_label' => 'pat-70002-'.Str::random(6)]);

        Livewire::test(ListAuditEvents::class)
            ->filterTable('subject', ['subject_id' => 70001])
            ->assertCanSeeTableRecords([$forPatient])
            ->assertCanNotSeeTableRecords([$otherPatient]);
    }

    public function test_the_resource_is_read_only(): void
    {
        $this->assertFalse(AuditEventResource::canCreate());

        // Only List and View pages exist: no create, no edit route.
        $this->assertSame(['index', 'view'], array_keys(AuditEventResource::getPages()));
    }
}
