<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\MatchDispatchService;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use App\Services\StaffPick\MatchNotificationService;
use App\Services\StaffPick\SmsService;
use App\Services\StaffPick\TierResponseScorer;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Feature\FeatureTest;

class MatchDispatchTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    private ProviderTier $platinum;

    private ProviderTier $gold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Physical Therapy',
            'abbreviation' => 'PT',
            'is_active' => true,
        ]);
        $this->platinum = ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Platinum', 'priority' => 1, 'response_window_minutes' => 120, 'is_active' => true]);
        $this->gold = ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Gold', 'priority' => 2, 'response_window_minutes' => 60, 'is_active' => true]);
    }

    private function provider(ProviderTier $tier): Provider
    {
        return Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'tier_id' => $tier->id,
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
            'preferred_contact_channel' => Provider::CHANNEL_PORTAL, // bell-only delivery, no external calls
        ]);
    }

    private function unmatchedCase(): IntakeRequest
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);

        return IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'subject_id' => $subject->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);
    }

    /**
     * MatchDispatchService with a faked eligibility set (real scorer/notifier/sms — sms
     * is never invoked for portal-channel providers).
     *
     * @param  array<int, Provider>  $eligible
     */
    private function service(array $eligible): MatchDispatchService
    {
        $results = collect($eligible)->map(fn (Provider $p): MatchingResult => new MatchingResult($p, 1.0, true, false, []));

        $engine = Mockery::mock(MatchingEngine::class);
        $engine->shouldReceive('match')->andReturn($results);

        return new MatchDispatchService(
            $engine,
            new TierResponseScorer,
            app(MatchNotificationService::class),
            app(SmsService::class),
        );
    }

    public function test_dispatch_offers_the_top_ranked_provider(): void
    {
        $gold = $this->provider($this->gold);
        $platinum = $this->provider($this->platinum);
        $case = $this->unmatchedCase();

        // Engine returns them gold-first; the scorer must surface Platinum (priority 1).
        $this->service([$gold, $platinum])->dispatch($case);

        $case->refresh();
        $this->assertSame(IntakeRequest::STATUS_MATCH_SENT, $case->status);
        $this->assertSame($platinum->id, $case->current_match_provider_id);
        $this->assertNotNull($case->last_match_sent_at);

        $offer = $case->assignmentOffers()->where('status', AssignmentOffer::STATUS_PENDING)->first();
        $this->assertSame($platinum->id, (int) $offer->provider_id);
        $this->assertSame('Platinum', $offer->tier_at_offer);
        $this->assertSame(120, $offer->response_window_minutes);
    }

    public function test_timeout_expires_the_offer_and_cascades(): void
    {
        $platinum = $this->provider($this->platinum);
        $gold = $this->provider($this->gold);
        $case = $this->unmatchedCase();
        $service = $this->service([$platinum, $gold]);

        $service->dispatch($case);
        $first = $case->assignmentOffers()->where('provider_id', $platinum->id)->first();

        $service->handleTimeout($case->refresh());

        $first->refresh();
        $this->assertSame(AssignmentOffer::STATUS_EXPIRED, $first->status);
        $this->assertNotNull($first->expired_at);

        $case->refresh();
        $this->assertSame(1, $case->cascade_attempt);
        $this->assertSame(IntakeRequest::STATUS_MATCH_SENT, $case->status);
        $this->assertSame($gold->id, $case->current_match_provider_id); // cascaded to next
    }

    public function test_rejection_cascades_to_the_next_provider(): void
    {
        $platinum = $this->provider($this->platinum);
        $gold = $this->provider($this->gold);
        $case = $this->unmatchedCase();
        $service = $this->service([$platinum, $gold]);

        $service->dispatch($case);
        $offer = $case->assignmentOffers()->where('provider_id', $platinum->id)->first();

        $service->handleRejection($case->refresh(), $offer);

        $this->assertSame(AssignmentOffer::STATUS_DECLINED, $offer->refresh()->status);
        $case->refresh();
        $this->assertSame(1, $case->cascade_attempt);
        $this->assertSame($gold->id, $case->current_match_provider_id);
    }

    public function test_pool_exhaustion_escalates(): void
    {
        $platinum = $this->provider($this->platinum);
        $case = $this->unmatchedCase();
        $service = $this->service([$platinum]); // only one eligible

        $service->dispatch($case);
        $offer = $case->assignmentOffers()->where('provider_id', $platinum->id)->first();

        $service->handleRejection($case->refresh(), $offer);

        $case->refresh();
        $this->assertSame(IntakeRequest::STATUS_ESCALATED, $case->status);
        $this->assertNotNull($case->escalated_at);
    }

    public function test_notification_gate_suppresses_a_disabled_channel(): void
    {
        Mail::fake();

        $optedOut = $this->createTenantAdmin($this->tenant);
        $this->tenant->users()->updateExistingPivot($optedOut->id, [
            'notification_preferences' => json_encode(['match_accepted' => ['in_app' => false]]),
        ]);
        $default = $this->createTenantAdmin($this->tenant);

        $platinum = $this->provider($this->platinum);
        $case = $this->unmatchedCase();
        $service = $this->service([$platinum]);
        $service->dispatch($case);
        $offer = $case->assignmentOffers()->first();

        $service->handleAcceptance($case->refresh(), $offer);

        $this->assertSame(0, $optedOut->notifications()->count(), 'opted-out staff should get no in-app bell');
        $this->assertGreaterThan(0, $default->notifications()->count(), 'default staff should get the bell');
    }

    public function test_acceptance_matches_and_sets_lead_clinician(): void
    {
        $platinum = $this->provider($this->platinum);
        $case = $this->unmatchedCase();
        $service = $this->service([$platinum]);

        $service->dispatch($case);
        $offer = $case->assignmentOffers()->where('provider_id', $platinum->id)->first();

        $service->handleAcceptance($case->refresh(), $offer);

        $this->assertSame(AssignmentOffer::STATUS_ACCEPTED, $offer->refresh()->status);
        $case->refresh();
        $this->assertSame(IntakeRequest::STATUS_MATCHED, $case->status);
        $this->assertSame($platinum->id, $case->lead_clinician_id);
        $this->assertSame(1, $case->assignments()->where('is_current', true)->count());
    }
}
