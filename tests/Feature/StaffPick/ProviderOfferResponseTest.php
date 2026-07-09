<?php

namespace Tests\Feature\StaffPick;

use App\Livewire\StaffPick\ProviderOfferResponse;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ProviderOfferResponseTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        // HTTP-level assertions (redirect to login, 403) need exception handling on.
        $this->withExceptionHandling();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
    }

    private function sentOfferFor(User $user, string $status = AssignmentOffer::STATUS_PENDING, ?CarbonInterface $expiresAt = null): AssignmentOffer
    {
        $provider = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'user_id' => $user->id,
        ]);

        $subject = Subject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Jordan',
            'last_name' => 'Vega',
            'address' => '12 Palm Way',
            'city' => 'Lake Worth',
            'state' => 'FL',
        ]);

        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
            'status' => 'offered',
            'start_date' => null,
        ]);

        return AssignmentOffer::create([
            'tenant_id' => $this->tenant->id,
            'intake_request_id' => $intake->id,
            'provider_id' => $provider->id,
            'offer_sequence' => 1,
            'status' => $status,
            'offered_at' => now(),
            'expires_at' => $expiresAt ?? now()->addMinutes(5),
            'token' => 'tok_'.Str::random(40),
        ]);
    }

    public function test_it_requires_authentication(): void
    {
        $offer = $this->sentOfferFor($this->createUser($this->tenant));

        $this->get('/offers/'.$offer->token)->assertRedirect(route('login'));
    }

    public function test_it_rejects_a_user_who_is_not_the_offers_provider(): void
    {
        $offer = $this->sentOfferFor($this->createUser($this->tenant));

        $this->actingAs($this->createUser($this->tenant));

        $this->get('/offers/'.$offer->token)->assertForbidden();
    }

    public function test_a_single_page_load_under_the_rate_limit_is_not_throttled(): void
    {
        $user = $this->createUser($this->tenant);
        $offer = $this->sentOfferFor($user);
        $this->actingAs($user);

        $this->get('/offers/'.$offer->token)->assertSuccessful();
    }

    public function test_the_thirty_first_page_load_in_a_minute_is_throttled(): void
    {
        $user = $this->createUser($this->tenant);
        $offer = $this->sentOfferFor($user);
        $this->actingAs($user);

        // throttle:30,1 — a compromised authenticated session can't flood token guesses at
        // the DB. 30 loads/minute succeed; the 31st is rejected before the route handler.
        for ($i = 0; $i < 30; $i++) {
            $this->get('/offers/'.$offer->token)->assertSuccessful();
        }

        $this->get('/offers/'.$offer->token)->assertStatus(429);
    }

    public function test_it_shows_the_full_case_to_the_owning_provider(): void
    {
        $user = $this->createUser($this->tenant);
        $offer = $this->sentOfferFor($user);
        $this->actingAs($user);

        Livewire::test(ProviderOfferResponse::class, ['token' => $offer->token])
            ->assertSuccessful()
            ->assertSee('Jordan')      // full patient name (PHI) is shown after login
            ->assertSee('12 Palm Way') // full address
            ->assertSee('Accept offer');
    }

    public function test_the_owning_provider_can_accept_the_offer(): void
    {
        Mail::fake();
        $this->createTenantAdmin($this->tenant);
        $user = $this->createUser($this->tenant);
        $offer = $this->sentOfferFor($user);
        $this->actingAs($user);

        Livewire::test(ProviderOfferResponse::class, ['token' => $offer->token])
            ->call('accept')
            ->assertHasNoErrors()
            ->assertSet('responded', true)
            ->assertSet('outcome', 'accepted');

        $this->assertSame(AssignmentOffer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertDatabaseHas('sp_assignments', [
            'intake_request_id' => $offer->intake_request_id,
            'provider_id' => $offer->provider_id,
            'status' => Assignment::STATUS_PENDING,
            'assigned_by_user_id' => $user->id,
        ]);
    }

    public function test_declining_rejects_a_reason_from_another_tenant(): void
    {
        $user = $this->createUser($this->tenant);
        $offer = $this->sentOfferFor($user);
        $foreignReason = DeclineReason::create([
            'tenant_id' => $this->createTenant()->id, // a different tenant's reason
            'name' => 'Foreign reason',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        Livewire::test(ProviderOfferResponse::class, ['token' => $offer->token])
            ->set('declineReasonId', $foreignReason->id)
            ->call('decline')
            ->assertHasErrors(['declineReasonId']);

        $this->assertSame(AssignmentOffer::STATUS_PENDING, $offer->fresh()->status);
    }

    public function test_an_expired_offer_shows_the_expired_message(): void
    {
        $user = $this->createUser($this->tenant);
        $offer = $this->sentOfferFor($user, AssignmentOffer::STATUS_PENDING, now()->subMinute());
        $this->actingAs($user);

        Livewire::test(ProviderOfferResponse::class, ['token' => $offer->token])
            ->assertSet('expired', true)
            ->assertSee('This offer has expired');
    }
}
