<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\SlackSettings;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class SlackSettingsPageTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        // No authed user yet (each test sets its own), so avoid the TenantSet event.
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    public function test_a_tenant_admin_can_access_the_page(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $this->assertTrue(SlackSettings::canAccess());

        Livewire::test(SlackSettings::class)
            ->assertSuccessful()
            ->assertSee('Inbound referrals');
    }

    public function test_a_non_admin_cannot_access_the_page(): void
    {
        $this->actingAs($this->createUser($this->tenant));

        $this->assertFalse(SlackSettings::canAccess());
    }

    public function test_it_saves_the_slack_settings(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->fillForm([
                'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/xyz',
                'slack_intake_keyword' => 'new referral',
                'slack_signing_secret' => 'super-secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sp_tenant_configs', [
            'tenant_id' => $this->tenant->id,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/xyz',
            'slack_intake_keyword' => 'new referral',
            'slack_signing_secret' => 'super-secret',
        ]);
    }

    public function test_an_invalid_webhook_url_is_rejected(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->fillForm(['slack_webhook_url' => 'not-a-url'])
            ->call('save')
            ->assertHasFormErrors(['slack_webhook_url']);
    }

    public function test_regenerating_the_token_persists_and_enables_the_inbound_url(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->assertSet('data.slack_inbound_token', null)
            ->call('regenerateToken')
            ->assertHasNoErrors()
            ->assertSet('data.slack_inbound_token', fn (?string $token): bool => filled($token));

        $config = TenantConfig::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertNotEmpty($config->slack_inbound_token);
    }
}
