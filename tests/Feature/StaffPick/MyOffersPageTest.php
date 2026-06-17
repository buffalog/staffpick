<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\MyOffers;
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
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class MyOffersPageTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function providerFor(User $user): Provider
    {
        return Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'user_id' => $user->id,
            'status' => Provider::STATUS_ACTIVE,
        ]);
    }

    private function offer(Provider $provider, string $status = AssignmentOffer::STATUS_PENDING, ?CarbonInterface $expiresAt = null): AssignmentOffer
    {
        $subject = Subject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Jordan',
            'last_name' => 'Vega',
            'city' => 'Lake Worth',
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

    public function test_it_renders_pending_offers_for_the_provider(): void
    {
        $user = $this->createUser($this->tenant);
        $provider = $this->providerFor($user);
        $this->offer($provider);
        $this->actingAs($user);

        Livewire::test(MyOffers::class)
            ->assertSuccessful()
            ->assertSee('Physical Therapy')
            ->assertSee('Lake Worth')
            ->assertSee('Accept');
    }

    public function test_it_shows_the_empty_state_when_there_are_no_pending_offers(): void
    {
        $user = $this->createUser($this->tenant);
        $this->providerFor($user);
        $this->actingAs($user);

        Livewire::test(MyOffers::class)
            ->assertSuccessful()
            ->assertSee('No pending offers');
    }

    public function test_accepting_an_offer_calls_the_service_and_redirects_to_the_case(): void
    {
        Mail::fake();
        $user = $this->createUser($this->tenant);
        $provider = $this->providerFor($user);
        $offer = $this->offer($provider);
        $this->actingAs($user);

        Livewire::test(MyOffers::class)
            ->call('accept', $offer->id)
            ->assertRedirect(route('staffpick.offer.respond', ['token' => $offer->token]));

        $this->assertSame(AssignmentOffer::STATUS_ACCEPTED, $offer->fresh()->status);
        $this->assertDatabaseHas('sp_assignments', [
            'intake_request_id' => $offer->intake_request_id,
            'provider_id' => $provider->id,
            'status' => Assignment::STATUS_PENDING,
        ]);
    }

    public function test_declining_requires_a_reason(): void
    {
        $user = $this->createUser($this->tenant);
        $provider = $this->providerFor($user);
        $offer = $this->offer($provider);
        DeclineReason::create(['tenant_id' => $this->tenant->id, 'name' => 'Too far', 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(MyOffers::class)
            ->callAction('decline', data: [], arguments: ['offer' => $offer->id])
            ->assertHasActionErrors(['decline_reason_id' => 'required']);

        $this->assertSame(AssignmentOffer::STATUS_PENDING, $offer->fresh()->status);
    }

    public function test_declining_with_a_reason_calls_the_service(): void
    {
        $user = $this->createUser($this->tenant);
        $provider = $this->providerFor($user);
        $offer = $this->offer($provider);
        $reason = DeclineReason::create(['tenant_id' => $this->tenant->id, 'name' => 'Too far', 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(MyOffers::class)
            ->callAction('decline', data: ['decline_reason_id' => $reason->id], arguments: ['offer' => $offer->id]);

        $this->assertSame(AssignmentOffer::STATUS_DECLINED, $offer->fresh()->status);
        $this->assertSame($reason->id, (int) $offer->fresh()->decline_reason_id);
    }

    public function test_declining_rejects_a_reason_from_another_tenant(): void
    {
        $user = $this->createUser($this->tenant);
        $provider = $this->providerFor($user);
        $offer = $this->offer($provider);
        $foreignReason = DeclineReason::create([
            'tenant_id' => $this->createTenant()->id,
            'name' => 'Foreign reason',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        Livewire::test(MyOffers::class)
            ->callAction('decline', data: ['decline_reason_id' => $foreignReason->id], arguments: ['offer' => $offer->id])
            ->assertHasActionErrors(['decline_reason_id']);

        $this->assertSame(AssignmentOffer::STATUS_PENDING, $offer->fresh()->status);
    }

    public function test_expired_offers_are_listed_but_not_actionable(): void
    {
        $user = $this->createUser($this->tenant);
        $provider = $this->providerFor($user);
        $expired = $this->offer($provider, AssignmentOffer::STATUS_PENDING, now()->subMinute());
        $this->actingAs($user);

        $page = Livewire::test(MyOffers::class)->assertSuccessful()->assertSee('Expired');

        // It's in the expired bucket, not the pending one.
        $this->assertTrue($page->instance()->expiredOffers()->contains('id', $expired->id));
        $this->assertFalse($page->instance()->pendingOffers()->contains('id', $expired->id));

        // Accepting an expired offer is a no-op.
        $page->call('accept', $expired->id);
        $this->assertSame(0, Assignment::where('intake_request_id', $expired->intake_request_id)->count());
    }

    public function test_a_user_without_a_provider_record_cannot_access(): void
    {
        $this->actingAs($this->createUser($this->tenant));

        $this->assertFalse(MyOffers::canAccess());
    }
}
