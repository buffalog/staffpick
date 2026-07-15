<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Smoke test for the merged-card + accordion View layout (PR F). Pure presentation, so the
 * real risk is a blade/render error — this drives the page and asserts the hero and accordion
 * scaffolding actually render. Date columns are left null: the local dblib driver can't parse
 * SQL Server `date` values (real pdo_sqlsrv on CI is fine).
 */
class ViewIntakeRequestPageTest extends FeatureTest
{
    public function test_view_page_renders_the_hero_card_and_accordions(): void
    {
        $tenant = $this->createTenant();
        $this->actingAs($this->createTenantAdmin($tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        $discipline = Discipline::create([
            'tenant_id' => $tenant->id,
            'name' => 'Physical Therapy',
            'abbreviation' => 'PT',
            'is_active' => true,
        ]);

        $subject = Subject::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Casey',
            'last_name' => 'Rivera',
        ]);

        $case = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
            'reference_number' => 'R-VIEW01',
            'authorization_number' => 'AUTH-123',
            'frequency' => '2x/week',
            'visit_type' => 'In-home',
        ]);

        Livewire::test(ViewIntakeRequest::class, ['record' => $case->id])
            ->assertOk()
            ->assertSee('Casey Rivera')   // hero title = patient name
            ->assertSee('Unmatched')      // status pill label via statusOptions()
            ->assertSee('R-VIEW01')       // ref line
            ->assertSee('Service')        // accordion titles render even while collapsed
            ->assertSee('Matching & Flags');
    }
}
