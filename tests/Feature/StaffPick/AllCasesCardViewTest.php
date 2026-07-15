<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\AllCases;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Smoke + toggle test for the All Cases card view (PR G). Pure presentation, so the real risk
 * is a blade/render error and the toggle wiring. Date columns left null — the local dblib
 * driver can't parse SQL Server `date` values (real pdo_sqlsrv on CI is fine).
 */
class AllCasesCardViewTest extends FeatureTest
{
    private function seedCase(): IntakeRequest
    {
        $discipline = Discipline::create([
            'tenant_id' => Filament::getTenant()->id,
            'name' => 'Physical Therapy',
            'abbreviation' => 'PT',
            'is_active' => true,
        ]);

        $subject = Subject::factory()->create([
            'tenant_id' => Filament::getTenant()->id,
            'first_name' => 'Jordan',
            'last_name' => 'Pena',
        ]);

        return IntakeRequest::factory()->create([
            'tenant_id' => Filament::getTenant()->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
            'reference_number' => 'R-CARD01',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createTenant();
        $this->actingAs($this->createTenantAdmin($tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);
    }

    public function test_all_cases_defaults_to_the_card_view_and_renders(): void
    {
        $this->seedCase();

        Livewire::test(AllCases::class)
            ->assertOk()
            ->assertSet('viewLayout', 'grid')
            ->assertSee('Jordan Pena')   // card header = patient name
            ->assertSee('Unmatched');    // status pill label
    }

    public function test_toggle_switches_to_the_table_view(): void
    {
        $this->seedCase();

        Livewire::test(AllCases::class)
            ->assertSet('viewLayout', 'grid')
            ->callAction('toggleLayout')
            ->assertSet('viewLayout', 'list');
    }

    public function test_card_view_search_matches_reference_and_patient_name(): void
    {
        $case = $this->seedCase();

        // The card View column carries no searchable text, so search is re-declared at the
        // table level in grid mode. Both the direct column and the dotted relationship resolve.
        Livewire::test(AllCases::class)
            ->assertSet('viewLayout', 'grid')
            ->searchTable('R-CARD01')
            ->assertCanSeeTableRecords([$case])
            ->searchTable('Pena') // subject.last_name via dot notation
            ->assertCanSeeTableRecords([$case])
            ->searchTable('zzz-no-match')
            ->assertCanNotSeeTableRecords([$case]);
    }
}
