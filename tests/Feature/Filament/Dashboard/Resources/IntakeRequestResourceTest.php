<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CreateIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\EditIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ListIntakeRequests;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Specialty;
use App\Models\StaffPick\Subject;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class IntakeRequestResourceTest extends FeatureTest
{
    private function actAsTenant($tenant): void
    {
        $user = $this->createTenantAdmin($tenant);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);
    }

    public function test_list_page_renders(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantAdmin($tenant);
        $this->actingAs($user);

        IntakeRequest::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(IntakeRequestResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertSuccessful();
    }

    public function test_list_only_shows_current_tenant_records(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        $mine = IntakeRequest::factory()->create(['tenant_id' => $tenant->id]);
        $theirs = IntakeRequest::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actAsTenant($tenant);

        Livewire::test(ListIntakeRequests::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_create_auto_fills_tenant_id_from_current_tenant(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::test(CreateIntakeRequest::class)
            ->fillForm([
                'subject_id' => $subject->id,
                'status' => 'unmatched',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_intake_requests', [
            'subject_id' => $subject->id,
            'status' => 'unmatched',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_new_case_defaults_to_draft_and_persists_as_unmatched(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::test(CreateIntakeRequest::class)
            ->assertFormSet(['status' => 'draft']) // create page shows draft, not a live status
            ->fillForm(['subject_id' => $subject->id]) // leave status at its draft default
            ->call('create')
            ->assertHasNoFormErrors();

        // Draft is a compose-time placeholder; a saved case starts life as unmatched.
        $this->assertDatabaseHas('sp_intake_requests', [
            'subject_id' => $subject->id,
            'status' => 'unmatched',
        ]);
        $this->assertDatabaseMissing('sp_intake_requests', [
            'subject_id' => $subject->id,
            'status' => 'draft',
        ]);
    }

    public function test_create_page_exposes_actions_as_header_actions(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateIntakeRequest::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertActionExists('createAnother')
            ->assertActionExists('cancel');
    }

    public function test_create_requires_a_subject(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateIntakeRequest::class)
            ->fillForm([
                'subject_id' => null,
                'status' => 'unmatched',
            ])
            ->call('create')
            ->assertHasFormErrors(['subject_id' => 'required']);
    }

    public function test_edit_updates_record(): void
    {
        $tenant = $this->createTenant();
        $record = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'unmatched',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(EditIntakeRequest::class, ['record' => $record->getKey()])
            ->fillForm(['status' => 'matched'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_intake_requests', [
            'id' => $record->id,
            'status' => 'matched',
        ]);
    }

    public function test_edit_page_exposes_save_and_cancel_as_header_actions(): void
    {
        $tenant = $this->createTenant();
        $record = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'unmatched',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(EditIntakeRequest::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertActionExists('save')
            ->assertActionExists('cancel')
            ->assertActionExists('delete');
    }

    public function test_edit_saves_partial_staffing_and_referring_clinician_fields(): void
    {
        $tenant = $this->createTenant();
        $lead = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Lena',
            'last_name' => 'Voss',
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $record = IntakeRequest::factory()->create(['tenant_id' => $tenant->id, 'status' => 'unmatched']);

        $this->actAsTenant($tenant);

        Livewire::test(EditIntakeRequest::class, ['record' => $record->getKey()])
            ->fillForm([
                'referring_clinician_name' => 'Dana Okafor, RN',
                'referring_clinician_phone' => '5615550199',
                'is_partial_staffing' => true,
                'assistant_clinician_name' => 'Marcus Lee, PTA',
                'lead_clinician_id' => $lead->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_intake_requests', [
            'id' => $record->id,
            'referring_clinician_name' => 'Dana Okafor, RN',
            'referring_clinician_phone' => '5615550199',
            'is_partial_staffing' => true,
            'assistant_clinician_name' => 'Marcus Lee, PTA',
            'lead_clinician_id' => $lead->id,
        ]);
    }

    public function test_view_page_shows_referring_clinician_and_partial_staffing(): void
    {
        $tenant = $this->createTenant();
        $lead = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Lena',
            'last_name' => 'Voss',
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $record = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'referring_clinician_name' => 'Dana Okafor, RN',
            'referring_clinician_phone' => '5615550199',
            'is_partial_staffing' => true,
            'assistant_clinician_name' => 'Marcus Lee, PTA',
            'lead_clinician_id' => $lead->id,
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ViewIntakeRequest::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertSee('Referring clinician')
            ->assertSee('Dana Okafor, RN')
            ->assertSee('Partial staffing')
            ->assertSee('Marcus Lee, PTA')
            ->assertSee('Lena Voss');
    }

    public function test_view_page_renders_record(): void
    {
        $tenant = $this->createTenant();
        $record = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'reference_number' => 'CASE-VIEW-1',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ViewIntakeRequest::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertSee('CASE-VIEW-1');
    }

    public function test_view_page_shows_the_matching_constraints(): void
    {
        $tenant = $this->createTenant();
        $record = IntakeRequest::factory()->create(['tenant_id' => $tenant->id]);

        $record->subject->update([
            'provider_gender_preference' => 'female',
            'language_preference' => 'Spanish',
        ]);

        $specialty = Specialty::create(['tenant_id' => $tenant->id, 'name' => 'Pediatrics', 'is_active' => true]);
        $record->specialties()->attach($specialty->id);

        $this->actAsTenant($tenant);

        Livewire::test(ViewIntakeRequest::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertSee('Gender Preference')
            ->assertSee('Female')
            ->assertSee('Language Preference')
            ->assertSee('Spanish')
            ->assertSee('Requested Specialties')
            ->assertSee('Pediatrics');
    }

    public function test_list_table_has_toggleable_preference_columns(): void
    {
        $tenant = $this->createTenant();
        IntakeRequest::factory()->create(['tenant_id' => $tenant->id]);

        $this->actAsTenant($tenant);

        Livewire::test(ListIntakeRequests::class)
            ->assertTableColumnExists('subject.provider_gender_preference')
            ->assertTableColumnExists('subject.language_preference');
    }
}
