<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Tests\Feature\FeatureTest;

class ProviderCalendarFeedTest extends FeatureTest
{
    private function providerWithActiveCase(Tenant $tenant, string $token): Provider
    {
        $provider = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Casey',
            'last_name' => 'Rivera',
            'calendar_token' => $token,
            'calendar_token_generated_at' => now(),
        ]);

        IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'lead_clinician_id' => $provider->id,
            'status' => 'matched',
            'evaluation_date' => '2026-07-01',
            'reference_number' => 'R-TEST01',
        ]);

        return $provider;
    }

    public function test_valid_token_returns_ical_feed(): void
    {
        $tenant = $this->createTenant();
        $token = 'valid'.str_repeat('a', 43);
        $this->providerWithActiveCase($tenant, $token);

        $response = $this->get("/calendar/{$tenant->uuid}/{$token}.ics");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('R-TEST01', $body);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260701', $body);
    }

    public function test_invalid_token_404s(): void
    {
        $this->withExceptionHandling();

        $tenant = $this->createTenant();
        $this->providerWithActiveCase($tenant, 'real'.str_repeat('b', 44));

        $this->get("/calendar/{$tenant->uuid}/wrongtoken.ics")->assertNotFound();
    }

    public function test_token_from_another_tenant_404s(): void
    {
        $this->withExceptionHandling();

        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $token = 'tok'.str_repeat('c', 45);
        $this->providerWithActiveCase($tenantA, $token);

        // Correct token but requested under the wrong tenant's uuid → 404.
        $this->get("/calendar/{$tenantB->uuid}/{$token}.ics")->assertNotFound();
    }
}
