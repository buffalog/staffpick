<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * The manual "Dispatch Offer" path (AssignsMatchedProviders) must not silently
 * double-commit a provider who already has a live offer on another case. It surfaces a
 * confirm-and-proceed prompt instead; an explicit confirm still sends (staff override).
 */
class ManualOfferSafetyTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    private ProviderTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
        $this->tier = ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Gold', 'priority' => 1]);

        $this->actingAs($this->createTenantAdmin($this->tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);
    }

    private function provider(): Provider
    {
        return Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'tier_id' => $this->tier->id,
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
        ]);
    }

    private function case(array $attributes = []): IntakeRequest
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);

        return IntakeRequest::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
        ], $attributes));
    }

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
            'token' => 'tok_'.Str::random(40),
        ]);
    }

    private function pendingOffersFor(IntakeRequest $case, Provider $provider): int
    {
        return AssignmentOffer::query()
            ->where('intake_request_id', $case->id)
            ->where('provider_id', $provider->id)
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->count();
    }

    public function test_offering_a_provider_with_a_live_offer_elsewhere_asks_to_confirm(): void
    {
        $provider = $this->provider();
        $caseA = $this->case(['reference_number' => 'R-AAA111']);
        $caseB = $this->case();
        $this->pendingOffer($caseA, $provider);

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('dispatchOfferToProvider', $caseB->id, $provider->id)
            ->assertSet('offerConfirmation.providerId', $provider->id)
            ->assertSet('offerConfirmation.conflictReference', 'R-AAA111');

        // Nothing sent to case B — the guard held.
        $this->assertSame(0, $this->pendingOffersFor($caseB, $provider));
    }

    public function test_confirming_sends_the_offer_despite_the_conflict(): void
    {
        $provider = $this->provider();
        $caseA = $this->case(['reference_number' => 'R-AAA222']);
        $caseB = $this->case();
        $this->pendingOffer($caseA, $provider);

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('dispatchOfferToProvider', $caseB->id, $provider->id, true)
            ->assertSet('offerConfirmation', null);

        $this->assertSame(1, $this->pendingOffersFor($caseB, $provider));
    }

    public function test_offering_a_free_provider_sends_without_confirmation(): void
    {
        $provider = $this->provider();
        $caseB = $this->case();

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('dispatchOfferToProvider', $caseB->id, $provider->id)
            ->assertSet('offerConfirmation', null);

        $this->assertSame(1, $this->pendingOffersFor($caseB, $provider));
    }

    public function test_a_same_case_pending_offer_does_not_trigger_the_conflict(): void
    {
        $provider = $this->provider();
        $caseB = $this->case();
        $this->pendingOffer($caseB, $provider); // already offered on THIS case, not another

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('dispatchOfferToProvider', $caseB->id, $provider->id)
            ->assertSet('offerConfirmation', null);
    }
}
