<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\CheckOfferExpiry;
use App\Jobs\StaffPick\DispatchOffers;
use App\Mail\StaffPick\AssignmentConfirmedReferrer;
use App\Mail\StaffPick\SchedulerAlert;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\OfferService;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

class OfferPipelineTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
    }

    private function service(): OfferService
    {
        return app(OfferService::class);
    }

    /** Provider at a latitude offset north of the subject (≈69 mi per degree). */
    private function providerAt(float $latOffset, array $attrs = []): Provider
    {
        return Provider::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'latitude' => 26.0 + $latOffset,
            'longitude' => -80.0,
            'preferred_contact_channel' => Provider::CHANNEL_EMAIL,
        ], $attrs));
    }

    private function intake(): IntakeRequest
    {
        $subject = Subject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => 26.0,
            'longitude' => -80.0,
        ]);

        return IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
            'status' => 'pending',
            'start_date' => null,
        ]);
    }

    private function offerBySequence(IntakeRequest $intake, int $sequence): AssignmentOffer
    {
        return AssignmentOffer::where('intake_request_id', $intake->id)->where('offer_sequence', $sequence)->firstOrFail();
    }

    public function test_dispatch_creates_offers_in_ranked_order_and_sends_the_first(): void
    {
        Mail::fake();
        $near = $this->providerAt(0.05); // ≈3.5 mi
        $far = $this->providerAt(0.10);  // ≈6.9 mi
        $intake = $this->intake();

        (new DispatchOffers($intake->id))->handle($this->service());

        $offers = AssignmentOffer::where('intake_request_id', $intake->id)->orderBy('offer_sequence')->get();
        $this->assertCount(2, $offers);
        $this->assertSame($near->id, $offers[0]->provider_id);
        $this->assertSame($far->id, $offers[1]->provider_id);
        $this->assertNotNull($offers[0]->offered_at, 'First offer should be sent.');
        $this->assertNull($offers[1]->offered_at, 'Second offer should be queued, not sent.');
        $this->assertNotEmpty($offers[0]->token);
        $this->assertSame('offered', $intake->fresh()->status);
    }

    public function test_offer_expiry_sends_the_next_offer(): void
    {
        Mail::fake();
        $this->providerAt(0.05);
        $this->providerAt(0.10);
        $intake = $this->intake();
        $this->service()->dispatchOffers($intake);

        $first = $this->offerBySequence($intake, 1);
        $first->update(['expires_at' => now()->subMinute()]);

        (new CheckOfferExpiry)->handle($this->service());

        $this->assertSame(AssignmentOffer::STATUS_EXPIRED, $first->fresh()->status);
        $this->assertNotNull($this->offerBySequence($intake, 2)->fresh()->offered_at);
    }

    public function test_accept_creates_assignment_withdraws_others_and_notifies(): void
    {
        Mail::fake();
        $this->createTenantAdmin($this->tenant); // scheduler recipient (bell + email)
        $actor = $this->createUser($this->tenant);
        $near = $this->providerAt(0.05, ['user_id' => $actor->id]);
        $this->providerAt(0.10);

        $source = ReferralSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Clinic',
            'email' => 'ref@acme.test',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);
        $intake = $this->intake();
        $intake->update(['referral_source_id' => $source->id]);

        $this->service()->dispatchOffers($intake);
        $offer = $this->offerBySequence($intake, 1);

        $this->service()->acceptOffer($offer, $actor);

        $this->assertDatabaseHas('sp_assignments', [
            'intake_request_id' => $intake->id,
            'provider_id' => $near->id,
            'status' => Assignment::STATUS_PENDING,
            'is_current' => true,
            'assigned_by_user_id' => $actor->id,
        ]);
        $this->assertSame(AssignmentOffer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertSame(AssignmentOffer::STATUS_EXPIRED, $this->offerBySequence($intake, 2)->fresh()->status);
        $this->assertSame('assigned_pending', $intake->fresh()->status);

        Mail::assertQueued(SchedulerAlert::class);
        Mail::assertQueued(AssignmentConfirmedReferrer::class);
    }

    public function test_decline_records_the_reason_and_sends_the_next_offer(): void
    {
        Mail::fake();
        $this->providerAt(0.05);
        $this->providerAt(0.10);
        $reason = DeclineReason::create(['tenant_id' => $this->tenant->id, 'name' => 'Too far', 'is_active' => true]);
        $intake = $this->intake();
        $this->service()->dispatchOffers($intake);

        $offer = $this->offerBySequence($intake, 1);
        $this->service()->declineOffer($offer, $reason->id);

        $this->assertSame(AssignmentOffer::STATUS_DECLINED, $offer->fresh()->status);
        $this->assertSame($reason->id, (int) $offer->fresh()->decline_reason_id);
        $this->assertNotNull($this->offerBySequence($intake, 2)->fresh()->offered_at);
    }

    public function test_queue_exhaustion_flags_no_clinicians_and_notifies_the_scheduler(): void
    {
        Mail::fake();
        $this->createTenantAdmin($this->tenant);
        $this->providerAt(0.05); // single provider
        $reason = DeclineReason::create(['tenant_id' => $this->tenant->id, 'name' => 'Unavailable', 'is_active' => true]);
        $intake = $this->intake();
        $this->service()->dispatchOffers($intake);

        $this->service()->declineOffer($this->offerBySequence($intake, 1), $reason->id);

        $this->assertSame('no_clinicians_available', $intake->fresh()->status);
        Mail::assertQueued(SchedulerAlert::class);
    }

    public function test_retrigger_with_radius_override_widens_the_net(): void
    {
        Mail::fake();
        $far = $this->providerAt(0.58, ['radius_max_miles' => 25]); // ≈40 mi, beyond the default cutoff
        $intake = $this->intake();

        $this->service()->dispatchOffers($intake);
        $this->assertSame(0, AssignmentOffer::where('intake_request_id', $intake->id)->count());
        $this->assertSame('no_clinicians_available', $intake->fresh()->status);

        $this->service()->dispatchOffers($intake, 50.0);

        $offers = AssignmentOffer::where('intake_request_id', $intake->id)->where('status', AssignmentOffer::STATUS_PENDING)->get();
        $this->assertCount(1, $offers);
        $this->assertSame($far->id, $offers->first()->provider_id);
        $this->assertSame('offered', $intake->fresh()->status);
    }
}
