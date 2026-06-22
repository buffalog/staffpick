<?php

namespace App\Providers\Filament;

use App\Constants\TenancyPermissionConstants;
use App\Http\Middleware\UpdateUserLastSeenAt;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ReferrerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('referrer')
            ->path('referrer')
            ->brandName('StaffPick · Referrer Portal')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->userMenuItems([
                Action::make('operations-dashboard')
                    ->label(__('Operations Dashboard'))
                    ->visible(function () {
                        $tenant = Filament::getTenant();

                        return $tenant && auth()->check() && auth()->user()->hasAnySpRole($tenant->id, [
                            TenancyPermissionConstants::ROLE_SP_ADMIN,
                            TenancyPermissionConstants::ROLE_SP_STAFF,
                        ]);
                    })
                    ->url(fn () => route('filament.dashboard.pages.dashboard', ['tenant' => Filament::getTenant()?->uuid]))
                    ->icon('heroicon-s-arrow-right-circle'),
                Action::make('provider-portal')
                    ->label(__('Provider Portal'))
                    ->visible(function () {
                        $tenant = Filament::getTenant();

                        return $tenant && auth()->check() && auth()->user()->hasSpRole(
                            $tenant->id, TenancyPermissionConstants::ROLE_SP_PROVIDER,
                        );
                    })
                    ->url(fn () => route('filament.provider.pages.dashboard', ['tenant' => Filament::getTenant()?->uuid]))
                    ->icon('heroicon-s-arrow-right-circle'),
            ])
            ->discoverResources(in: app_path('Filament/Referrer/Resources'), for: 'App\\Filament\\Referrer\\Resources')
            ->discoverPages(in: app_path('Filament/Referrer/Pages'), for: 'App\\Filament\\Referrer\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Referrer/Widgets'), for: 'App\\Filament\\Referrer\\Widgets')
            ->favicon(asset('images/favicon.ico'))
            ->databaseNotifications()
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->tenant(Tenant::class, 'uuid');
    }
}
