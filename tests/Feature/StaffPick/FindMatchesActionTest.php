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
        $this->actingAs($this->createUser($tenant));

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
}
