<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\TenantConfig;
use App\Services\StaffPick\SlackNotificationService;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * Per-tenant Slack integration settings: the outbound webhook URL + inbound trigger
 * keyword and signing secret, plus the tenant's inbound webhook URL (namespaced by a
 * regenerable token). Gated to tenant admins. Thin UI over the tenant's TenantConfig.
 */
class SlackSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'settings/slack';

    protected string $view = 'filament.dashboard.pages.slack-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('Slack Integration');
    }

    public static function getNavigationLabel(): string
    {
        return __('Slack Integration');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('StaffPick');
    }

    public static function canAccess(): bool
    {
        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS,
        );
    }

    public function mount(): void
    {
        $config = $this->config();

        $this->form->fill([
            'slack_webhook_url' => $config->slack_webhook_url,
            'slack_intake_keyword' => $config->slack_intake_keyword,
            'slack_signing_secret' => $config->slack_signing_secret,
            'slack_inbound_token' => $config->slack_inbound_token,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Outbound notifications'))
                    ->description(__('StaffPick posts intake, assignment, profile-review and credential-expiry alerts to this Slack Incoming Webhook. Leave blank to fall back to the global default (or to disable Slack for this workspace).'))
                    ->schema([
                        TextInput::make('slack_webhook_url')
                            ->label(__('Incoming webhook URL'))
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://hooks.slack.com/services/...')
                            // Restrict to genuine Slack Incoming Webhooks. Without this an admin
                            // could point the URL at internal infrastructure (db.railway.internal,
                            // cloud metadata, other services) and have the server make blind
                            // requests to it — SSRF — which the Test button triggers on demand.
                            // The trailing slash blocks hooks.slack.com.evil.com / userinfo tricks.
                            ->rules(['regex:~^https://hooks\.slack\.com/~'])
                            ->validationMessages(['regex' => __('Enter a Slack Incoming Webhook URL (https://hooks.slack.com/...).')])
                            ->columnSpanFull(),
                        Actions::make([
                            Action::make('testWebhook')
                                ->label(__('Send test message'))
                                ->icon(Heroicon::OutlinedPaperAirplane)
                                ->color('gray')
                                ->disabled(fn (Get $get): bool => blank($get('slack_webhook_url')))
                                ->action(fn () => $this->sendTest()),
                        ]),
                    ]),

                Section::make(__('Inbound referrals'))
                    ->description(__('Slack can create draft intakes. Add the URL below to your Slack app\'s Event Subscriptions, set the signing secret, and any message containing the keyword creates a draft intake with a reference number posted back.'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('slack_intake_keyword')
                                ->label(__('Trigger keyword'))
                                ->placeholder('new referral')
                                ->maxLength(100)
                                ->helperText(__('An inbound message containing this phrase creates a draft intake.')),
                            TextInput::make('slack_signing_secret')
                                ->label(__('Signing secret'))
                                ->password()
                                ->revealable()
                                ->maxLength(255)
                                ->helperText(__('From your Slack app. Falls back to the global secret if blank.')),
                        ]),
                        Hidden::make('slack_inbound_token'),
                        Placeholder::make('inbound_url')
                            ->label(__('Inbound webhook URL'))
                            ->content(fn (Get $get): View => view('filament.dashboard.partials.slack-inbound-url', [
                                'token' => $get('slack_inbound_token'),
                            ])),
                        Actions::make([
                            Action::make('regenerateToken')
                                ->label(fn (Get $get): string => filled($get('slack_inbound_token'))
                                    ? __('Regenerate inbound token')
                                    : __('Generate inbound token'))
                                ->icon(Heroicon::ArrowPath)
                                ->color('gray')
                                ->requiresConfirmation(fn (Get $get): bool => filled($get('slack_inbound_token')))
                                ->modalDescription(__('Regenerating invalidates the current inbound URL — you will need to update it in Slack.'))
                                ->action(fn () => $this->regenerateToken()),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $this->config()->update([
            'slack_webhook_url' => filled($state['slack_webhook_url'] ?? null) ? $state['slack_webhook_url'] : null,
            'slack_intake_keyword' => filled($state['slack_intake_keyword'] ?? null) ? $state['slack_intake_keyword'] : null,
            'slack_signing_secret' => filled($state['slack_signing_secret'] ?? null) ? $state['slack_signing_secret'] : null,
        ]);

        Notification::make()
            ->title(__('Slack settings saved'))
            ->success()
            ->send();
    }

    /**
     * Fire a test message to the tenant's configured outbound webhook so an admin can
     * confirm the integration end-to-end. Uses the saved configuration — save first.
     */
    public function sendTest(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant === null) {
            return;
        }

        if (blank($this->config()->slackWebhookUrl())) {
            Notification::make()
                ->title(__('No webhook configured'))
                ->body(__('Save an Incoming webhook URL first, then send a test.'))
                ->danger()
                ->send();

            return;
        }

        try {
            app(SlackNotificationService::class)->notifyText(
                $tenant->id,
                __('🧪 StaffPick test message from :tenant. Your Slack integration is working!', ['tenant' => $tenant->name]),
            );

            Notification::make()
                ->title(__('Test message sent'))
                ->body(__('Check your Slack channel for the test message.'))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('Could not send test message'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function regenerateToken(): void
    {
        $token = bin2hex(random_bytes(20));

        $this->config()->forceFill(['slack_inbound_token' => $token])->save();
        $this->data['slack_inbound_token'] = $token;

        Notification::make()
            ->title(__('Inbound token generated'))
            ->body(__('Update the inbound webhook URL in your Slack app.'))
            ->success()
            ->send();
    }

    private function config(): TenantConfig
    {
        return TenantConfig::firstOrCreate(['tenant_id' => Filament::getTenant()->getKey()]);
    }
}
