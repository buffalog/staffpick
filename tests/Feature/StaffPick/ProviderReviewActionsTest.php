<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\Providers\Pages\ListProviders;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ProviderReviewActionsTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->actingAs($this->createUser($this->tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);
    }

    private function pendingProvider(): Provider
    {
        return Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Provider::STATUS_PENDING,
            'is_active' => false,
        ]);
    }

    public function test_approving_a_pending_application_activates_the_provider(): void
    {
        $provider = $this->pendingProvider();

        Livewire::test(ListProviders::class)
            ->callAction(TestAction::make('approve')->table($provider))
            ->assertHasNoErrors();

        $provider->refresh();
        $this->assertSame(Provider::STATUS_ACTIVE, $provider->status);
        $this->assertTrue((bool) $provider->is_active);
    }

    public function test_rejecting_records_the_reason(): void
    {
        $provider = $this->pendingProvider();

        Livewire::test(ListProviders::class)
            ->callAction(TestAction::make('reject')->table($provider), ['reason' => 'Expired license on file.'])
            ->assertHasNoErrors();

        $provider->refresh();
        $this->assertSame(Provider::STATUS_REJECTED, $provider->status);
        $this->assertSame('Expired license on file.', $provider->rejection_reason);
    }

    public function test_review_actions_are_hidden_for_non_pending_providers(): void
    {
        $active = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Provider::STATUS_ACTIVE,
        ]);

        Livewire::test(ListProviders::class)
            ->assertActionHidden(TestAction::make('approve')->table($active))
            ->assertActionHidden(TestAction::make('reject')->table($active));
    }
}
