<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
use App\Models\StaffPick\Subject;
use Tests\Feature\FeatureTest;

/**
 * The authenticated dashboard panel renders Subjects/IntakeRequests (PHI). It must ship NO
 * analytics: gtag sends document.title (the patient last name) to Google (no BAA), and the
 * tracking_scripts partial is a raw-HTML injection point on PHI pages. This forces analytics
 * fully "on" via config so the assertion proves the render hook is gone, not that a config
 * gate happened to be closed.
 */
class AnalyticsNotOnPhiPanelTest extends FeatureTest
{
    public function test_dashboard_phi_page_ships_no_analytics(): void
    {
        // Force every analytics gate open: no cookie-consent wall, a sentinel GA id, and a
        // sentinel raw tracking script. If the head hook still existed, all of this would render.
        config([
            'cookie-consent.enabled' => false,
            'app.google_tracking_id' => 'G-SENTINEL999',
            'app.tracking_scripts' => '<script>SENTINEL_TRACKER_INJECTED</script>',
        ]);

        $tenant = $this->createTenant();
        $this->actingAs($this->createTenantAdmin($tenant));

        // A PHI resource page (patient last name is this resource's record title).
        Subject::factory()->create(['tenant_id' => $tenant->id, 'last_name' => 'Zzyxpatient']);

        $response = $this->get(SubjectResource::getUrl('index', [], true, 'dashboard', tenant: $tenant));
        $response->assertSuccessful();

        $body = $response->getContent();
        $this->assertStringNotContainsString('googletagmanager.com', $body);
        $this->assertStringNotContainsString('G-SENTINEL999', $body);
        $this->assertStringNotContainsString('SENTINEL_TRACKER_INJECTED', $body);
    }
}
