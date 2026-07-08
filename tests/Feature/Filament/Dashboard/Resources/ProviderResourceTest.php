<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Providers\Pages\CreateProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\EditProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\ListProviders;
use App\Filament\Dashboard\Resources\Providers\Pages\ViewProvider;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Specialty;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ProviderResourceTest extends FeatureTest
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

        Provider::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(ProviderResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertSuccessful();
    }

    public function test_list_only_shows_current_tenant_providers(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        $mine = Provider::factory()->create(['tenant_id' => $tenant->id]);
        $theirs = Provider::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actAsTenant($tenant);

        Livewire::test(ListProviders::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_gender_filter_narrows_the_grid(): void
    {
        $tenant = $this->createTenant();
        $female = Provider::factory()->create(['tenant_id' => $tenant->id, 'gender' => 'female', 'last_name' => 'Genderfemale']);
        $male = Provider::factory()->create(['tenant_id' => $tenant->id, 'gender' => 'male', 'last_name' => 'Gendermale']);

        $this->actAsTenant($tenant);

        Livewire::test(ListProviders::class)
            ->filterTable('gender', 'female')
            ->assertCanSeeTableRecords([$female])
            ->assertCanNotSeeTableRecords([$male]);
    }

    public function test_language_filter_narrows_the_grid(): void
    {
        $tenant = $this->createTenant();
        // firstOrCreate: the languages seeder may already own the 'es' row on the shared DB.
        $spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish']);

        $speaks = Provider::factory()->create(['tenant_id' => $tenant->id, 'last_name' => 'Langspeaks']);
        $speaks->languages()->attach($spanish->id, ['is_primary' => true]);
        $silent = Provider::factory()->create(['tenant_id' => $tenant->id, 'last_name' => 'Langsilent']);

        $this->actAsTenant($tenant);

        Livewire::test(ListProviders::class)
            ->filterTable('languages', $spanish->id)
            ->assertCanSeeTableRecords([$speaks])
            ->assertCanNotSeeTableRecords([$silent]);
    }

    public function test_search_box_narrows_records_in_grid_layout(): void
    {
        $tenant = $this->createTenant();
        $match = Provider::factory()->create(['tenant_id' => $tenant->id, 'last_name' => 'Zephyrenko']);
        $other = Provider::factory()->create(['tenant_id' => $tenant->id, 'last_name' => 'Abramsonqx']);

        $this->actAsTenant($tenant);

        // Default layout is the card grid; search must still narrow it.
        Livewire::test(ListProviders::class)
            ->assertSet('viewLayout', 'grid')
            ->searchTable('Zephyrenko')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_create_auto_fills_tenant_id_from_current_tenant(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateProvider::class)
            ->fillForm([
                'first_name' => 'Avery',
                'last_name' => 'Stone',
                'email' => 'avery.stone@example.com',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // tenant_id was never supplied to the form — the BelongsToTenant trait fills it.
        $this->assertDatabaseHas('sp_providers', [
            'first_name' => 'Avery',
            'last_name' => 'Stone',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_create_requires_first_and_last_name(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateProvider::class)
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

    public function test_edit_updates_provider(): void
    {
        $tenant = $this->createTenant();
        $provider = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'last_name' => 'Original',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(EditProvider::class, ['record' => $provider->getKey()])
            ->fillForm(['last_name' => 'Updated'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_providers', [
            'id' => $provider->id,
            'last_name' => 'Updated',
        ]);
    }

    public function test_view_page_renders_provider(): void
    {
        $tenant = $this->createTenant();
        $provider = Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Wren',
            'last_name' => 'Calloway',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ViewProvider::class, ['record' => $provider->getKey()])
            ->assertSuccessful()
            ->assertSee('Wren')
            ->assertSee('Calloway');
    }

    public function test_create_persists_the_other_specialty_write_in_note(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy', 'abbreviation' => 'PT']);
        $other = Specialty::create(['tenant_id' => $tenant->id, 'name' => Specialty::OTHER_NAME]);
        $discipline->specialties()->attach($other->id);

        Livewire::test(CreateProvider::class)
            ->fillForm([
                'first_name' => 'Avery',
                'last_name' => 'Stone',
                'status' => 'active',
                'discipline_id' => $discipline->id,
                'specialties' => [$other->id],
                'specialty_other_note' => 'Aquatic Therapy',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $provider = Provider::where('tenant_id', $tenant->id)->where('first_name', 'Avery')->firstOrFail();
        $this->assertSame('Aquatic Therapy', $provider->specialties()->where('sp_specialties.id', $other->id)->first()->pivot->notes);
    }
}
