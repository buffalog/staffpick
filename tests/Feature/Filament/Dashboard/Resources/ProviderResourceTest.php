<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Providers\Pages\CreateProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\EditProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\ListProviders;
use App\Filament\Dashboard\Resources\Providers\Pages\ViewProvider;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Models\StaffPick\Provider;
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
}
