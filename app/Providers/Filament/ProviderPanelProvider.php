<?php

namespace App\Providers\Filament;

use App\Filament\Dashboard\Pages\ProviderProfile;
use App\Filament\Provider\Pages\MyCases;
use App\Filament\Provider\Pages\MyOffers;
use App\Filament\Provider\Pages\ProviderHome;
use App\Http\Middleware\UpdateUserLastSeenAt;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class ProviderPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('provider')
            ->path('provider')
            ->brandName('StaffPick · Provider Portal')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->userMenuItems([
                Action::make('my-profile')
                    ->label(__('My Profile'))
                    ->url(fn () => ProviderProfile::getUrl(panel: 'dashboard', tenant: Filament::getTenant()))
                    ->icon('heroicon-s-user-circle'),
                Action::make('my-cases')
                    ->label(__('My Cases'))
                    ->url(fn () => MyCases::getUrl(panel: 'provider', tenant: Filament::getTenant()))
                    ->icon('heroicon-s-calendar-days'),
                Action::make('case-offerings')
                    ->label(__('Case Matches'))
                    ->url(fn () => MyOffers::getUrl(panel: 'provider', tenant: Filament::getTenant()))
                    ->icon('heroicon-s-inbox-arrow-down'),
            ])
            ->discoverResources(in: app_path('Filament/Provider/Resources'), for: 'App\\Filament\\Provider\\Resources')
            ->discoverPages(in: app_path('Filament/Provider/Pages'), for: 'App\\Filament\\Provider\\Pages')
            ->pages([
                ProviderHome::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Provider/Widgets'), for: 'App\\Filament\\Provider\\Widgets')
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
