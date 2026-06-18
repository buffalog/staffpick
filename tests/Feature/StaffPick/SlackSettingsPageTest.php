<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\SlackSettings;
use App\Jobs\StaffPick\SendSlackNotification;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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
        ]);

        // The signing secret is encrypted at rest: the model decrypts it on read,
        // but the raw column must not contain the plaintext.
        $config = TenantConfig::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('super-secret', $config->slack_signing_secret);
        $this->assertNotSame('super-secret', $config->getRawOriginal('slack_signing_secret'));
    }

    public function test_an_invalid_webhook_url_is_rejected(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->fillForm(['slack_webhook_url' => 'not-a-url'])
            ->call('save')
            ->assertHasFormErrors(['slack_webhook_url']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function ssrfWebhookUrls(): array
    {
        return [
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'internal db host' => ['http://db.railway.internal:5432'],
            'localhost' => ['https://localhost/x'],
            'lookalike subdomain' => ['https://hooks.slack.com.evil.com/services/x'],
            'userinfo trick' => ['https://hooks.slack.com@evil.com/x'],
            'plain http slack' => ['http://hooks.slack.com/services/x'],
        ];
    }

    #[DataProvider('ssrfWebhookUrls')]
    public function test_it_rejects_non_slack_webhook_urls(string $url): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->fillForm(['slack_webhook_url' => $url])
            ->call('save')
            ->assertHasFormErrors(['slack_webhook_url']);

        $this->assertDatabaseMissing('sp_tenant_configs', [
            'tenant_id' => $this->tenant->id,
            'slack_webhook_url' => $url,
        ]);
    }

    public function test_send_test_posts_a_test_message_when_a_webhook_is_configured(): void
    {
        Queue::fake();
        TenantConfig::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['slack_webhook_url' => 'https://hooks.slack.com/services/T0/B0/test'],
        );
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->call('sendTest')
            ->assertHasNoErrors();

        Queue::assertPushed(SendSlackNotification::class, function (SendSlackNotification $job): bool {
            return $job->webhookUrl === 'https://hooks.slack.com/services/T0/B0/test'
                && str_contains(json_encode($job->payload), 'test message');
        });
    }

    public function test_send_test_is_a_no_op_without_a_webhook(): void
    {
        Queue::fake();
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->call('sendTest')
            ->assertHasNoErrors();

        Queue::assertNothingPushed();
    }

    public function test_the_inbound_url_shows_a_copy_button_once_a_token_exists(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        Livewire::test(SlackSettings::class)
            ->call('regenerateToken')
            ->assertSee('Copy');
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
