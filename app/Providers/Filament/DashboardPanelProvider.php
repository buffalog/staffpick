<?php

namespace App\Providers\Filament;

use App\Constants\AnnouncementPlacement;
use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Auth\TenantLogin;
use App\Filament\Dashboard\Pages\CreateWorkspace;
use App\Filament\Dashboard\Pages\ProviderProfile;
use App\Filament\Dashboard\Pages\TenantSettings;
use App\Filament\Dashboard\Pages\TwoFactorAuth\TwoFactorAuth;
use App\Http\Middleware\UpdateUserLastSeenAt;
use App\Livewire\AddressForm;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dashboard')
            ->path('dashboard')
            // Tenant-aware login: standard email/password (always available — the
            // super-admin escape hatch) with SSO/social buttons injected via the
            // AUTH_LOGIN_FORM_BEFORE hook below.
            ->login(TenantLogin::class)
            ->colors([
                'primary' => Color::Teal,
            ])
            ->userMenuItems([
                Action::make('admin-panel')
                    ->label(__('Admin Panel'))
                    ->visible(
                        fn () => auth()->user()->isAdmin()
                    )
                    ->url(fn () => route('filament.admin.pages.dashboard'))
                    ->icon('heroicon-s-cog-8-tooth'),
                Action::make('workspace-settings')
                    ->label(__('Workspace Settings'))
                    ->visible(
                        function () {
                            $tenantPermissionService = app(TenantPermissionService::class);

                            return $tenantPermissionService->tenantUserHasPermissionTo(
                                Filament::getTenant(),
                                auth()->user(),
                                TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS
                            );
                        }
                    )
                    ->icon('heroicon-s-cog-8-tooth')
                    ->url(fn () => TenantSettings::getUrl()),
                Action::make('my-provider-profile')
                    ->label(__('My Provider Profile'))
                    ->visible(fn () => ProviderProfile::canAccess())
                    ->url(fn () => ProviderProfile::getUrl())
                    ->icon('heroicon-s-user-circle'),
                Action::make('switch-to-provider-portal')
                    ->label(__('Provider Portal'))
                    ->visible(function () {
                        $tenant = Filament::getTenant();

                        return $tenant && auth()->check() && auth()->user()->hasSpRole(
                            $tenant->id, TenancyPermissionConstants::ROLE_SP_PROVIDER,
                        );
                    })
                    ->url(fn () => route('filament.provider.pages.dashboard', ['tenant' => Filament::getTenant()?->uuid]))
                    ->icon('heroicon-s-arrow-right-circle'),
                Action::make('switch-to-referrer-portal')
                    ->label(__('Referrer Portal'))
                    ->visible(function () {
                        $tenant = Filament::getTenant();

                        return $tenant && auth()->check() && auth()->user()->hasSpRole(
                            $tenant->id, TenancyPermissionConstants::ROLE_SP_REFERRER,
                        );
                    })
                    ->url(fn () => route('filament.referrer.pages.dashboard', ['tenant' => Filament::getTenant()?->uuid]))
                    ->icon('heroicon-s-arrow-right-circle'),
                Action::make('two-factor-auth')
                    ->label(__('2-Factor Authentication'))
                    ->visible(
                        fn () => config('app.two_factor_auth_enabled')
                    )
                    ->url(fn () => TwoFactorAuth::getUrl())
                    ->icon('heroicon-s-lock-closed'),
            ])
            ->discoverResources(in: app_path('Filament/Dashboard/Resources'), for: 'App\\Filament\\Dashboard\\Resources')
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\\Filament\\Dashboard\\Pages')
            ->pages([
                Dashboard::class,
                CreateWorkspace::class,
            ])
            ->favicon(asset('images/favicon.ico'))
            ->databaseNotifications()
            ->viteTheme('resources/css/filament/dashboard/theme.css')
            ->discoverWidgets(in: app_path('Filament/Dashboard/Widgets'), for: 'App\\Filament\\Dashboard\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                UpdateUserLastSeenAt::class,
            ])
            ->renderHook('panels::head.start', function () {
                return view('components.layouts.partials.analytics');
            })
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.dashboard.partials.leaflet-assets')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.dashboard.partials.sortable-assets')->render(),
            )
            ->navigationGroups([
                // Order + default collapse state of the sidebar groups. Dispatch and
                // Administration stay expanded; Settings starts collapsed (infrequent).
                // No group icons: Filament forbids a group icon when its items already
                // carry icons (which all of these do).
                NavigationGroup::make()
                    ->label(__('Dispatch')),
                NavigationGroup::make()
                    ->label(__('Credentialing')),
                NavigationGroup::make()
                    ->label(__('Settings'))
                    ->collapsed(),
                NavigationGroup::make()
                    ->label(__('Administration')),
                // Listed last so Help is pinned to the bottom of the sidebar (Filament
                // renders truly-ungrouped items at the top, so a trailing group is the
                // only way to bottom-pin it).
                NavigationGroup::make()
                    ->label(__('Support')),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render("@livewire('announcement.view', ['placement' => '".AnnouncementPlacement::USER_DASHBOARD->value."'])")
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('@livewire(\App\Livewire\StaffPick\HelpSlideOver::class)')
            )
            // Inject the tenant SSO button + Google social button above the login form.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.dashboard.partials.login-sso')->render(),
                scopes: TenantLogin::class,
            )
            ->authMiddleware([
                Authenticate::class,
            ])->plugins([
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true, // Sets the 'account' link in the panel User Menu (default = true)
                        shouldRegisterNavigation: false, // Adds a main navigation item for the My Profile page (default = false)
                        hasAvatars: false, // Enables the avatar upload form component (default = false)
                        slug: 'my-profile' // Sets the slug for the profile page (default = 'my-profile')
                    )
                    ->myProfileComponents([
                        AddressForm::class,
                    ]),
            ])
            ->tenantMenuItems([
                Action::make('create')
                    ->label(__('New Workspace'))
                    ->url(fn () => CreateWorkspace::getUrl())
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn () => config('app.allow_user_to_create_tenants_from_dashboard', false)),
            ])
            ->tenantMenu()
            ->tenant(Tenant::class, 'uuid');
    }
}
