<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\CredentialingPolicies;
use App\Models\StaffPick\CredentialDocumentType;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class CredentialingPoliciesPageTest extends FeatureTest
{
    public function test_tenant_admin_sees_credential_types_with_policy_columns(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createTenantAdmin($tenant);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        $type = CredentialDocumentType::create([
            'tenant_id' => $tenant->id,
            'name' => 'State License (PT)',
            'verification_method' => 'manual',
        ]);

        Livewire::test(CredentialingPolicies::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$type])
            ->assertTableColumnExists('is_required')
            ->assertTableColumnExists('deactivate_on_expiry')
            ->assertTableColumnExists('expiry_warning_days');
    }

    public function test_non_admin_cannot_access(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        $this->assertFalse(CredentialingPolicies::canAccess());
    }
}
