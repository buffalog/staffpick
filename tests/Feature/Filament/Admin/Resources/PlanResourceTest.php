<?php

namespace Tests\Feature\Filament\Admin\Resources;

use App\Filament\Admin\Resources\Plans\PlanResource;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\FeatureTest;

// Quarantined: SaaSykit billing boilerplate, StaffPick has no checkout/plans. See CI triage.
#[Group('saasykit-unused')]
class PlanResourceTest extends FeatureTest
{
    public function test_list(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get(PlanResource::getUrl('index', [], true, 'admin'))->assertSuccessful();

        $response->assertStatus(200);
    }
}
