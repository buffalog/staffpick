<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\MatchingEngine;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class FindMatchesActionTest extends FeatureTest
{
    private function actAsTenant(Tenant $tenant): void
    {
        $this->actingAs($this->createTenantAdmin($tenant));

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);
    }

    /**
     * @return array{0: Tenant, 1: IntakeRequest, 2: Provider, 3: Provider}
     */
    private function seedCase(): array
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $tier = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Gold', 'priority' => 1]);

        $inRange = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'discipline_id' => $discipline->id,
            'tier_id' => $tier->id,
            'last_name' => 'Closematch',
            'latitude' => 40.05,
            'longitude' => -75.0,
            'radius_max_miles' => 25,
        ]);

        $outOfRange = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'discipline_id' => $discipline->id,
            'tier_id' => $tier->id,
            'last_name' => 'Faraway',
            'latitude' => 41.0, // ~69 miles north, well beyond radius
            'longitude' => -75.0,
            'radius_max_miles' => 25,
        ]);

        $subject = Subject::factory()->create([
            'tenant_id' => $tenant->id,
            'latitude' => 40.0,
            'longitude' => -75.0,
        ]);
        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
        ]);

        return [$tenant, $intake, $inRange, $outOfRange];
    }

    public function test_find_matches_action_is_registered_on_the_view_page(): void
    {
        [$tenant, $intake] = $this->seedCase();
        $this->actAsTenant($tenant);

        Livewire::test(ViewIntakeRequest::class, ['record' => $intake->id])
            ->assertActionExists('findMatches')
            ->assertActionHasLabel('findMatches', 'Find Matches');
    }

    public function test_partial_staffing_shows_a_notice_in_the_matches_modal(): void
    {
        [$tenant, $intake] = $this->seedCase();
        $intake->update(['is_partial_staffing' => true]);
        $this->actAsTenant($tenant);

        $html = view('staffpick.intake-requests.matches', [
            'record' => $intake->fresh(),
            'results' => app(MatchingEngine::class)->match($intake->fresh()),
        ])->render();

        $this->assertStringContainsString('Partial staffing', $html);
        $this->assertStringContainsString('Match a lead clinician only', $html);
    }

    public function test_engine_resolves_eligible_providers_under_filament_tenant_scope(): void
    {
        [$tenant, $intake, $inRange, $outOfRange] = $this->seedCase();
        $this->actAsTenant($tenant);

        // The action runs the engine inside a Filament request (tenant global scope
        // active); confirm it still returns only the in-range provider.
        $results = app(MatchingEngine::class)->match($intake->fresh());

        $this->assertSame([$inRange->id], $results->map(fn ($r) => $r->provider->id)->all());
        $this->assertNotContains($outOfRange->id, $results->map(fn ($r) => $r->provider->id)->all());
    }

    public function test_modal_orders_providers_by_the_scorer_not_distance(): void
    {
        $tenant = $this->createTenant();
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $gold = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Gold', 'priority' => 1]);
        $silver = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Silver', 'priority' => 2]);

        // Insert the nearer, lower-tier provider FIRST: natural query order and distance both
        // put Abbott ahead. Only the scorer's tier precedence surfaces the gold provider first.
        Provider::factory()->create([
            'tenant_id' => $tenant->id, 'discipline_id' => $discipline->id, 'tier_id' => $silver->id,
            'last_name' => 'Abbott', 'latitude' => 40.02, 'longitude' => -75.0,
            'radius_max_miles' => 50, 'is_preferred' => false,
        ]);
        Provider::factory()->create([
            'tenant_id' => $tenant->id, 'discipline_id' => $discipline->id, 'tier_id' => $gold->id,
            'last_name' => 'Zephyr', 'latitude' => 40.30, 'longitude' => -75.0,
            'radius_max_miles' => 50, 'is_preferred' => false,
        ]);

        $subject = Subject::factory()->create(['tenant_id' => $tenant->id, 'latitude' => 40.0, 'longitude' => -75.0]);
        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id, 'subject_id' => $subject->id, 'discipline_id' => $discipline->id,
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ViewIntakeRequest::class, ['record' => $intake->id])
            ->mountAction('findMatches')
            ->assertSeeInOrder(['Zephyr', 'Abbott']);
    }

    public function test_assigning_a_matched_provider_creates_an_offer_and_advances_the_case(): void
    {
        [$tenant, $intake, $inRange] = $this->seedCase();
        $this->actAsTenant($tenant);

        Livewire::test(ViewIntakeRequest::class, ['record' => $intake->id])
            ->call('assignProvider', $intake->id, $inRange->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sp_assignments', [
            'intake_request_id' => $intake->id,
            'provider_id' => $inRange->id,
            'status' => 'offered',
            'is_current' => true,
            'is_manual' => true,
        ]);
        $this->assertDatabaseHas('sp_intake_requests', [
            'id' => $intake->id,
            'status' => 'assigned_pending',
        ]);
    }
}
