<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\ReferralSources\Pages\CreateReferralSource;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\EditReferralSource;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\ListReferralSources;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\ViewReferralSource;
use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use App\Models\StaffPick\ReferralSource;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ReferralSourceResourceTest extends FeatureTest
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

        ReferralSource::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(ReferralSourceResource::getUrl('index', [], true, 'dashboard', tenant: $tenant))
            ->assertSuccessful();
    }

    public function test_list_only_shows_current_tenant_records(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        $mine = ReferralSource::factory()->create(['tenant_id' => $tenant->id]);
        $theirs = ReferralSource::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actAsTenant($tenant);

        Livewire::test(ListReferralSources::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_create_auto_fills_tenant_id_from_current_tenant(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateReferralSource::class)
            ->fillForm([
                'name' => 'Sunrise Home Health',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_referral_sources', [
            'name' => 'Sunrise Home Health',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_create_requires_name(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        Livewire::test(CreateReferralSource::class)
            ->fillForm(['name' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_edit_updates_record(): void
    {
        $tenant = $this->createTenant();
        $record = ReferralSource::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Original Name',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(EditReferralSource::class, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_referral_sources', [
            'id' => $record->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_view_page_renders_record(): void
    {
        $tenant = $this->createTenant();
        $record = ReferralSource::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Lakeside Clinic',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ViewReferralSource::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertSee('Lakeside Clinic');
    }
}
