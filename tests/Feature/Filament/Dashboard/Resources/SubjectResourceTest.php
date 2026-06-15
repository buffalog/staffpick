<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Dashboard\Resources\Subjects\Pages\EditSubject;
use App\Filament\Dashboard\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Dashboard\Resources\Subjects\Pages\ViewSubject;
use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
use App\Models\StaffPick\Subject;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class SubjectResourceTest extends FeatureTest
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

        Subject::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(SubjectResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertSuccessful();
    }

    public function test_list_only_shows_current_tenant_records(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        $mine = Subject::factory()->create(['tenant_id' => $tenant->id]);
        $theirs = Subject::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actAsTenant($tenant);

        Livewire::test(ListSubjects::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_create_auto_fills_tenant_id_from_current_tenant(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateSubject::class)
            ->fillForm([
                'first_name' => 'Maya',
                'last_name' => 'Reyes',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_subjects', [
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_create_requires_first_and_last_name(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateSubject::class)
            ->fillForm([
                'first_name' => null,
                'last_name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'first_name' => 'required',
                'last_name' => 'required',
            ]);
    }

    public function test_edit_updates_record(): void
    {
        $tenant = $this->createTenant();
        $record = Subject::factory()->create([
            'tenant_id' => $tenant->id,
            'last_name' => 'Original',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(EditSubject::class, ['record' => $record->getKey()])
            ->fillForm(['last_name' => 'Updated'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_subjects', [
            'id' => $record->id,
            'last_name' => 'Updated',
        ]);
    }

    public function test_view_page_renders_record(): void
    {
        $tenant = $this->createTenant();
        $record = Subject::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Theo',
            'last_name' => 'Nakamura',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ViewSubject::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertSee('Theo')
            ->assertSee('Nakamura');
    }
}
