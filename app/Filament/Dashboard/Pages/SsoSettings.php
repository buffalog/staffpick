<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\StaffPick\Auth\SsoConfigValidator;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Per-tenant SSO configuration. Gated to tenant admins via the tenant-settings
 * permission — which also keeps it hidden from a super admin browsing in via bypass
 * (they aren't a tenant member, so they don't hold the permission). The client secret
 * is stored encrypted (TenantConfig cast); the form only re-saves it when re-entered.
 */
class SsoSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $slug = 'settings/sso';

    protected string $view = 'filament.dashboard.pages.sso-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('Single Sign-On');
    }

    public static function getNavigationLabel(): string
    {
        return __('Single Sign-On');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
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
            'sso_provider' => $config->sso_provider,
            'sso_client_id' => $config->sso_client_id,
            // Secret intentionally left blank in the form — re-enter to change it.
            'sso_client_secret' => null,
            'sso_domain' => $config->sso_domain,
            'sso_enabled' => (bool) $config->sso_enabled,
            'sso_required' => (bool) $config->sso_required,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Single sign-on'))
                    ->description(__('Let staff sign in with your identity provider. Email/password always remains available as a fallback (and for super admins).'))
                    ->schema([
                        Select::make('sso_provider')
                            ->label(__('Provider'))
                            ->options([
                                'google_workspace' => __('Google Workspace'),
                                'okta' => __('Okta (coming soon)'),
                                'azure_ad' => __('Microsoft Entra ID / Azure AD (coming soon)'),
                                'saml' => __('SAML (coming soon)'),
                            ])
                            ->disableOptionWhen(fn (string $value): bool => $value !== 'google_workspace')
                            ->live()
                            ->placeholder(__('None')),
                        TextInput::make('sso_domain')
                            ->label(__('Email domain'))
                            ->placeholder('fcts.com')
                            ->helperText(__('Only users whose email ends with @this-domain may sign in via SSO.'))
                            ->maxLength(255),
                        TextInput::make('sso_client_id')
                            ->label(__('Client ID'))
                            ->maxLength(255),
                        TextInput::make('sso_client_secret')
                            ->label(__('Client secret'))
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->helperText(__('Stored encrypted. Leave blank to keep the current secret.')),
                        Toggle::make('sso_enabled')
                            ->label(__('Enable SSO'))
                            ->helperText(__('Shows a “Sign in with SSO” button on this workspace\'s login page.')),
                        Toggle::make('sso_required')
                            ->label(__('Require SSO for staff'))
                            ->helperText(__('Warning: hides the email/password form for staff. Super admins always retain email/password access via the escape hatch on the login page.')),
                        Actions::make([
                            Action::make('testConnection')
                                ->label(__('Test SSO connection'))
                                ->icon(Heroicon::OutlinedBolt)
                                ->color('gray')
                                ->action(fn () => $this->testConnection()),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $attributes = [
            'sso_provider' => filled($state['sso_provider'] ?? null) ? $state['sso_provider'] : null,
            'sso_client_id' => filled($state['sso_client_id'] ?? null) ? $state['sso_client_id'] : null,
            'sso_domain' => filled($state['sso_domain'] ?? null) ? $state['sso_domain'] : null,
            'sso_enabled' => (bool) ($state['sso_enabled'] ?? false),
            'sso_required' => (bool) ($state['sso_required'] ?? false),
        ];

        // Only overwrite the encrypted secret when a new one was entered.
        if (filled($state['sso_client_secret'] ?? null)) {
            $attributes['sso_client_secret'] = $state['sso_client_secret'];
        }

        $this->config()->update($attributes);

        Notification::make()->title(__('SSO settings saved'))->success()->send();
    }

    /**
     * Validate the entered (unsaved) configuration without persisting it, delegating
     * the structural check to {@see SsoConfigValidator} and rendering its result.
     */
    public function testConnection(): void
    {
        abort_unless(static::canAccess(), 403);

        $result = app(SsoConfigValidator::class)->check(
            Filament::getTenant(),
            $this->config(),
            $this->form->getState(),
        );

        $notification = Notification::make()
            ->title($result->title)
            ->body($result->body);

        match ($result->status) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            default => $notification->danger(),
        };

        $notification->send();
    }

    private function config(): TenantConfig
    {
        return TenantConfig::firstOrCreate(['tenant_id' => Filament::getTenant()->getKey()]);
    }
}
