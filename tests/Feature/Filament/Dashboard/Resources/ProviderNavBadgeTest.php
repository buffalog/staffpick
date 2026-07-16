<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderApplication;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

/**
 * The Providers nav badge must count providers awaiting credentialing (status = pending),
 * tenant-scoped, NOT submitted ProviderApplications (the long-standing display bug).
 */
class ProviderNavBadgeTest extends FeatureTest
{
    private function actAsTenant(Tenant $tenant): void
    {
        $this->actingAs($this->createTenantAdmin($tenant));

        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);
    }

    private function provider(Tenant $tenant, string $status): Provider
    {
        return Provider::factory()->create(['tenant_id' => $tenant->id, 'status' => $status]);
    }

    public function test_the_badge_counts_only_pending_providers(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        $this->provider($tenant, Provider::STATUS_PENDING);
        $this->provider($tenant, Provider::STATUS_PENDING);
        $this->provider($tenant, Provider::STATUS_PENDING);
        $this->provider($tenant, Provider::STATUS_ACTIVE);
        $this->provider($tenant, Provider::STATUS_INACTIVE);

        $this->assertSame('3', ProviderResource::getNavigationBadge());
    }

    public function test_the_badge_is_hidden_with_no_pending_providers(): void
    {
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        // A non-pending provider exists, but nothing awaiting credentialing.
        $this->provider($tenant, Provider::STATUS_ACTIVE);

        $this->assertNull(ProviderResource::getNavigationBadge());
    }

    public function test_a_submitted_application_does_not_change_the_badge(): void
    {
        // Regression guard for the actual bug: the badge must reflect the provider roster, not
        // ProviderApplication. A submitted application in the same tenant must not move it.
        $tenant = $this->createTenant();
        $this->actAsTenant($tenant);

        $this->provider($tenant, Provider::STATUS_PENDING);

        ProviderApplication::create([
            'tenant_id' => $tenant->id,
            'application_token' => 'app-'.Str::random(40),
            'status' => ProviderApplication::STATUS_SUBMITTED,
            'first_name' => 'Sam',
            'last_name' => 'Applicant',
            'email' => 'sam.applicant.'.Str::random(8).'@example.com',
        ]);

        // Still 1: only the pending provider counts, the submitted application is ignored.
        $this->assertSame('1', ProviderResource::getNavigationBadge());
    }
}
