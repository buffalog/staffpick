<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Tests\Feature\FeatureTest;

/**
 * StaffPick PHI resources must be restricted to tenant admins (HIPAA). A
 * low-privilege tenant member must NOT be able to view/edit/delete patient data.
 */
class StaffPickAuthorizationTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    /**
     * @return array<int, class-string>
     */
    private function phiResources(): array
    {
        return [
            IntakeRequestResource::class,
            SubjectResource::class,
            ProviderResource::class,
            ReferralSourceResource::class,
        ];
    }

    public function test_a_non_admin_tenant_member_cannot_access_any_phi_resource(): void
    {
        $this->actingAs($this->createUser($this->tenant)); // attached, no admin role

        foreach ($this->phiResources() as $resource) {
            $this->assertFalse($resource::canViewAny(), "{$resource} must be admin-gated");
            $this->assertFalse($resource::canCreate(), "{$resource} create must be admin-gated");
        }
    }

    public function test_a_tenant_admin_can_access_phi_resources(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        foreach ($this->phiResources() as $resource) {
            $this->assertTrue($resource::canViewAny(), "{$resource} should allow a tenant admin");
        }
    }

    public function test_a_non_admin_gets_403_visiting_the_intake_requests_index(): void
    {
        $this->withExceptionHandling();
        $this->actingAs($this->createUser($this->tenant));

        $this->get(IntakeRequestResource::getUrl('index', [], true, 'dashboard', tenant: $this->tenant))
            ->assertForbidden();
    }
}
