<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Providers\Pages\ListProviders;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class TenantEntityLabelTest extends FeatureTest
{
    private function actAsTenant(Tenant $tenant): void
    {
        $this->actingAs($this->createTenantAdmin($tenant));

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);
    }

    public function test_falls_back_to_default_without_tenant_context(): void
    {
        // No Filament tenant resolved (e.g. console / admin panel) — defaults apply.
        $this->assertSame('Provider', ProviderResource::getModelLabel());
        $this->assertSame('Providers', ProviderResource::getNavigationLabel());
        $this->assertSame('Discipline', TenantConfig::entityLabel('discipline', __('Discipline')));
    }

    public function test_defaults_when_tenant_has_no_config_row(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        $this->assertSame('Provider', ProviderResource::getModelLabel());
        $this->assertSame('Providers', ProviderResource::getPluralModelLabel());
        $this->assertSame('Providers', ProviderResource::getNavigationLabel());
        $this->assertSame('Subject', SubjectResource::getModelLabel());
        $this->assertSame('Intake Requests', IntakeRequestResource::getNavigationLabel());
        $this->assertSame('Discipline', TenantConfig::entityLabel('discipline', __('Discipline')));
    }

    public function test_custom_labels_from_config_drive_singular_and_derived_plural(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'entity_label_provider' => 'Clinician',
            'entity_label_subject' => 'Patient',
            'entity_label_intake_request' => 'Service Request',
            'entity_label_discipline' => 'Service Line',
        ]);

        $this->actAsTenant($tenant);

        $this->assertSame('Clinician', ProviderResource::getModelLabel());
        $this->assertSame('Clinicians', ProviderResource::getPluralModelLabel());
        $this->assertSame('Clinicians', ProviderResource::getNavigationLabel());

        $this->assertSame('Patient', SubjectResource::getModelLabel());
        $this->assertSame('Patients', SubjectResource::getNavigationLabel());

        $this->assertSame('Service Request', IntakeRequestResource::getModelLabel());
        $this->assertSame('Service Requests', IntakeRequestResource::getNavigationLabel());

        $this->assertSame('Service Line', TenantConfig::entityLabel('discipline', __('Discipline')));
    }

    public function test_blank_config_value_falls_back_to_default(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'entity_label_provider' => '',
        ]);

        $this->actAsTenant($tenant);

        $this->assertSame('Provider', ProviderResource::getModelLabel());
    }

    public function test_labels_are_scoped_per_tenant(): void
    {
        $renamed = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $renamed->id,
            'entity_label_provider' => 'Clinician',
        ]);
        $default = $this->createTenant();

        $this->actAsTenant($renamed);
        $this->assertSame('Clinician', ProviderResource::getModelLabel());

        $this->actAsTenant($default);
        $this->assertSame('Provider', ProviderResource::getModelLabel());
    }

    public function test_list_page_heading_reflects_the_configured_label(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'entity_label_provider' => 'Clinician',
        ]);

        $this->actAsTenant($tenant);

        Livewire::test(ListProviders::class)
            ->assertSuccessful()
            ->assertSee('Clinicians');
    }
}
