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
 * The manual "Assign" path (AssignsMatchedProviders) must not silently commit a provider
 * who already has a live offer out on another case — same over-commitment the offer path
 * guards (finding 9). It surfaces a confirm-and-proceed prompt; an explicit confirm still
 * assigns (staff override).
 */
class ManualAssignSafetyTest extends FeatureTest
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

    public function test_assigning_a_provider_with_a_live_offer_elsewhere_asks_to_confirm(): void
    {
        $provider = $this->provider();
        $caseA = $this->case(['reference_number' => 'R-ASN111']);
        $caseB = $this->case();
        $this->pendingOffer($caseA, $provider);

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('assignProvider', $caseB->id, $provider->id)
            ->assertSet('assignConfirmation.providerId', $provider->id)
            ->assertSet('assignConfirmation.conflictReference', 'R-ASN111');

        // Nothing committed on case B.
        $this->assertNotSame(IntakeRequest::STATUS_MATCHED, $caseB->refresh()->status);
        $this->assertSame(0, $caseB->assignments()->count());
    }

    public function test_confirming_assigns_despite_the_conflict(): void
    {
        $provider = $this->provider();
        $caseA = $this->case(['reference_number' => 'R-ASN222']);
        $caseB = $this->case();
        $this->pendingOffer($caseA, $provider);

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('assignProvider', $caseB->id, $provider->id, true)
            ->assertSet('assignConfirmation', null);

        $caseB->refresh();
        $this->assertSame(IntakeRequest::STATUS_MATCHED, $caseB->status);
        $this->assertSame($provider->id, $caseB->lead_clinician_id);
    }

    public function test_assigning_a_free_provider_assigns_without_confirmation(): void
    {
        $provider = $this->provider();
        $caseB = $this->case();

        Livewire::test(ViewIntakeRequest::class, ['record' => $caseB->id])
            ->call('assignProvider', $caseB->id, $provider->id)
            ->assertSet('assignConfirmation', null);

        $caseB->refresh();
        $this->assertSame(IntakeRequest::STATUS_MATCHED, $caseB->status);
        $this->assertSame($provider->id, $caseB->lead_clinician_id);
    }
}
