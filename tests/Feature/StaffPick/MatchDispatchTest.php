<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\AssignmentService;
use App\Services\StaffPick\MatchDispatchService;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use App\Services\StaffPick\MatchNotificationService;
use App\Services\StaffPick\SmsService;
use App\Services\StaffPick\TierResponseScorer;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    /**
     * @param  array<string, mixed>  $subjectAttributes
     * @param  array<string, mixed>  $caseAttributes
     */
    private function unmatchedCase(array $subjectAttributes = [], array $caseAttributes = []): IntakeRequest
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, ...$subjectAttributes]);

        return IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'subject_id' => $subject->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
            ...$caseAttributes,
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

    public function test_an_ungeocoded_case_escalates_with_the_needs_coordinates_reason(): void
    {
        $case = $this->unmatchedCase(['latitude' => null, 'longitude' => null]);

        $this->service([])->dispatch($case);

        $case->refresh();
        $this->assertSame(IntakeRequest::STATUS_ESCALATED, $case->status);
        $this->assertSame(IntakeRequest::ESCALATION_NEEDS_COORDINATES, $case->escalation_reason);
    }

    public function test_a_genuinely_exhausted_pool_escalates_with_the_default_reason(): void
    {
        // Geocoded, discipline set — nothing structural is missing, so an empty pool really
        // IS an empty pool. Guards the accurate-reason change against swallowing exhaustion.
        $case = $this->unmatchedCase(['latitude' => 26.82, 'longitude' => -80.05]);

        $this->service([])->dispatch($case);

        $case->refresh();
        $this->assertSame(IntakeRequest::STATUS_ESCALATED, $case->status);
        $this->assertSame(IntakeRequest::ESCALATION_POOL_EXHAUSTED, $case->escalation_reason);
    }

    public function test_a_case_with_no_discipline_escalates_with_the_no_discipline_reason(): void
    {
        $case = $this->unmatchedCase(
            ['latitude' => 26.82, 'longitude' => -80.05],
            ['discipline_id' => null],
        );

        $this->service([])->dispatch($case);

        $this->assertSame(IntakeRequest::ESCALATION_NO_DISCIPLINE, $case->refresh()->escalation_reason);
    }

    public function test_offer_to_a_non_speaker_is_flagged_language_warning(): void
    {
        $provider = $this->provider($this->platinum); // speaks nothing
        $case = $this->unmatchedCase(['language_preference' => 'Spanish']);

        $this->service([$provider])->dispatch($case);

        $this->assertTrue((bool) $case->assignmentOffers()->first()->language_warning);
    }

    public function test_offer_to_a_speaker_is_not_flagged(): void
    {
        $spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish']);
        $provider = $this->provider($this->platinum);
        $provider->languages()->attach($spanish->id, ['is_primary' => true]);
        $case = $this->unmatchedCase(['language_preference' => 'Spanish']);

        $this->service([$provider])->dispatch($case);

        $this->assertFalse((bool) $case->assignmentOffers()->first()->language_warning);
    }

    public function test_offer_is_not_flagged_when_the_subject_states_no_preference(): void
    {
        $provider = $this->provider($this->platinum);
        $case = $this->unmatchedCase(); // factory leaves language_preference null

        $this->service([$provider])->dispatch($case);

        $this->assertFalse((bool) $case->assignmentOffers()->first()->language_warning);
    }

    /**
     * The race: a concurrent cascade commits a pending offer for the top provider in the
     * window between dispatch()'s busy-provider read and sendOffer()'s insert. Simulated by
     * writing that offer on TransactionBeginning — which fires exactly inside that window —
     * so no real threads are needed.
     */
    public function test_dispatch_cascades_when_the_top_provider_is_taken_mid_flight(): void
    {
        $platinum = $this->provider($this->platinum);
        $gold = $this->provider($this->gold);
        $case = $this->unmatchedCase();
        $otherCase = $this->unmatchedCase();

        $raced = false;
        Event::listen(TransactionBeginning::class, function () use (&$raced, $platinum, $otherCase): void {
            if ($raced) {
                return;
            }

            $raced = true;

            AssignmentOffer::create([
                'tenant_id' => $this->tenant->id,
                'intake_request_id' => $otherCase->id,
                'provider_id' => $platinum->id,
                'offer_sequence' => 1,
                'status' => AssignmentOffer::STATUS_PENDING,
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(30),
                'delivery_channel' => Provider::CHANNEL_PORTAL,
                'token' => Str::random(48),
            ]);
        });

        $this->service([$platinum, $gold])->dispatch($case);

        $this->assertTrue($raced, 'the simulated concurrent offer never fired');

        $offers = $case->assignmentOffers()->where('status', AssignmentOffer::STATUS_PENDING)->get();
        $this->assertCount(1, $offers, 'the taken provider must not be double-offered');
        $this->assertSame($gold->id, (int) $offers->first()->provider_id);
        $this->assertSame($gold->id, (int) $case->refresh()->current_match_provider_id);
    }

    public function test_a_case_with_an_open_offer_does_not_get_a_second_one(): void
    {
        $platinum = $this->provider($this->platinum);
        $gold = $this->provider($this->gold);
        $case = $this->unmatchedCase();
        $service = $this->service([$platinum, $gold]);

        $service->dispatch($case);
        $service->dispatch($case->refresh()); // re-entrant run (e.g. a job racing a staff action)

        $this->assertSame(1, $case->assignmentOffers()->where('status', AssignmentOffer::STATUS_PENDING)->count());
        $this->assertSame($platinum->id, (int) $case->refresh()->current_match_provider_id);
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

    /** A pending, delivered offer to $provider on $case. */
    private function pendingOffer(IntakeRequest $case, Provider $provider): AssignmentOffer
    {
        return AssignmentOffer::create([
            'tenant_id' => $this->tenant->id,
            'intake_request_id' => $case->id,
            'provider_id' => $provider->id,
            'offer_sequence' => 1,
            'status' => AssignmentOffer::STATUS_PENDING,
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'delivery_channel' => Provider::CHANNEL_PORTAL,
            'token' => Str::random(48),
        ]);
    }

    public function test_manual_assign_withdraws_the_cases_open_offer(): void
    {
        $x = $this->provider($this->platinum);
        $y = $this->provider($this->gold);
        $case = $this->unmatchedCase();
        $yOffer = $this->pendingOffer($case, $y);

        app(AssignmentService::class)->assign($case, $x);

        $this->assertSame(AssignmentOffer::STATUS_WITHDRAWN, $yOffer->refresh()->status);
        $case->refresh();
        $this->assertSame(IntakeRequest::STATUS_MATCHED, $case->status);
        $this->assertSame($x->id, $case->lead_clinician_id);
        $this->assertNull($case->current_match_provider_id);
    }

    public function test_a_withdrawn_offer_cannot_clobber_the_manual_assignment(): void
    {
        $x = $this->provider($this->platinum);
        $y = $this->provider($this->gold);
        $case = $this->unmatchedCase();
        $yOffer = $this->pendingOffer($case, $y);

        app(AssignmentService::class)->assign($case, $x); // withdraws Y's offer (commit 1)

        // Y tries to accept the now-withdrawn offer — must no-op, not overwrite the placement.
        $this->service([])->handleAcceptance($case->refresh(), $yOffer->refresh());

        $case->refresh();
        $this->assertSame($x->id, $case->lead_clinician_id, 'manual lead must survive a stale accept');
        $this->assertSame(IntakeRequest::STATUS_MATCHED, $case->status);
        $this->assertSame(0, $case->assignments()->where('is_current', true)->where('provider_id', $y->id)->count());
    }

    public function test_accepting_a_non_pending_offer_is_a_no_op(): void
    {
        $default = $this->createTenantAdmin($this->tenant);

        $y = $this->provider($this->gold);
        $case = $this->unmatchedCase(); // stays UNMATCHED — never advanced
        $withdrawn = $this->pendingOffer($case, $y);
        $withdrawn->update(['status' => AssignmentOffer::STATUS_WITHDRAWN, 'responded_at' => now()]);

        $this->service([])->handleAcceptance($case->refresh(), $withdrawn->refresh());

        $this->assertSame(AssignmentOffer::STATUS_WITHDRAWN, $withdrawn->refresh()->status);
        $this->assertSame(IntakeRequest::STATUS_UNMATCHED, $case->refresh()->status);
        $this->assertSame(0, $case->assignments()->count());
        $this->assertSame(0, $default->notifications()->count(), 'no accepted-event bell for a no-op accept');
    }
}
