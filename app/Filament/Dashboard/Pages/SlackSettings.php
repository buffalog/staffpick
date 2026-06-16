<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\TenantConfig;
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
use Illuminate\Support\HtmlString;

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
                            ->columnSpanFull(),
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
                            ->content(fn (Get $get): HtmlString => new HtmlString(
                                filled($get('slack_inbound_token'))
                                    ? '<code class="text-sm">'.e(route('staffpick.slack.inbound', ['token' => $get('slack_inbound_token')])).'</code>'
                                    : '<span class="text-sm text-gray-500">'.e(__('Not generated yet — generate a token to enable inbound.')).'</span>'
                            )),
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
