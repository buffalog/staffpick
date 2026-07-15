<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\ProcessMatchTimeouts;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\MatchDispatchService;
use App\Services\StaffPick\TenantContext;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

class ProcessMatchTimeoutsJobTest extends FeatureTest
{
    /**
     * A MATCH_SENT case with a pending offer, both stamped $minutesAgo minutes back.
     */
    private function sentCase(Tenant $tenant, int $minutesAgo): AssignmentOffer
    {
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $discipline->id]);
        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);

        $case = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
            'status' => IntakeRequest::STATUS_MATCH_SENT,
            'last_match_sent_at' => now()->subMinutes($minutesAgo),
            'current_match_provider_id' => $provider->id,
        ]);

        return AssignmentOffer::create([
            'tenant_id' => $tenant->id,
            'intake_request_id' => $case->id,
            'provider_id' => $provider->id,
            'offer_sequence' => 1,
            'status' => AssignmentOffer::STATUS_PENDING,
            'response_window_minutes' => 30,
            'offered_at' => now()->subMinutes($minutesAgo),
            'expires_at' => now()->subMinutes($minutesAgo)->addMinutes(30),
            'token' => Str::random(48),
        ]);
    }

    public function test_the_sweep_runs_per_tenant_and_isolates_each(): void
    {
        // Tenant A: window long elapsed (200 min > 30) → should time out.
        $tenantA = $this->createTenant();
        $staleOffer = $this->sentCase($tenantA, 200);

        // Tenant B: just sent (0 min < 30) → must be left alone.
        $tenantB = $this->createTenant();
        $freshOffer = $this->sentCase($tenantB, 0);

        (new ProcessMatchTimeouts)->handle(app(MatchDispatchService::class), app(TenantContext::class));

        // A's offer expired (handleTimeout ran); B's is untouched — proves the per-tenant sweep
        // processed A without reaching into B.
        $this->assertSame(AssignmentOffer::STATUS_EXPIRED, $staleOffer->refresh()->status);
        $this->assertSame(AssignmentOffer::STATUS_PENDING, $freshOffer->refresh()->status);
        // Two-tenant assertion, no single context — read the case explicitly cross-tenant.
        $this->assertSame(IntakeRequest::STATUS_MATCH_SENT, $freshOffer->intakeRequest()->crossTenant()->first()->status);
    }
}
