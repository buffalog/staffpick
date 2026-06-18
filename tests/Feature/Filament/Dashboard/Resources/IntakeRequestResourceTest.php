<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CreateIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\EditIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ListIntakeRequests;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Models\StaffPick\IntakeRequest;
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
                'status' => 'pending',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_intake_requests', [
            'subject_id' => $subject->id,
            'status' => 'pending',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_create_requires_a_subject(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateIntakeRequest::class)
            ->fillForm([
                'subject_id' => null,
                'status' => 'pending',
            ])
            ->call('create')
            ->assertHasFormErrors(['subject_id' => 'required']);
    }

    public function test_edit_updates_record(): void
    {
        $tenant = $this->createTenant();
        $record = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(EditIntakeRequest::class, ['record' => $record->getKey()])
            ->fillForm(['status' => 'active'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_intake_requests', [
            'id' => $record->id,
            'status' => 'active',
        ]);
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
