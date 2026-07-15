<?php

namespace App\Providers;

use App\Services\PaymentProviders\Creem\CreemProvider;
use App\Services\PaymentProviders\LemonSqueezy\LemonSqueezyProvider;
use App\Services\PaymentProviders\Offline\OfflineProvider;
use App\Services\PaymentProviders\Paddle\PaddleProvider;
use App\Services\PaymentProviders\PaymentService;
use App\Services\PaymentProviders\Polar\PolarProvider;
use App\Services\PaymentProviders\Stripe\StripeProvider;
use App\Services\StaffPick\ProviderScorer;
use App\Services\StaffPick\TenantContext;
use App\Services\StaffPick\TierResponseScorer;
use App\Services\UserVerificationService;
use App\Services\VerificationProviders\PingramProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // payment providers
        $this->app->tag([
            StripeProvider::class,
            PaddleProvider::class,
            LemonSqueezyProvider::class,
            CreemProvider::class,
            PolarProvider::class,
            OfflineProvider::class,
        ], 'payment-providers');

        $this->app->bind(PaymentService::class, function () {
            return new PaymentService(...$this->app->tagged('payment-providers'));
        });

        // verification providers
        $this->app->tag([
            PingramProvider::class,
        ], 'verification-providers');

        $this->app->afterResolving(UserVerificationService::class, function (UserVerificationService $service) {
            $service->setVerificationProviders(...$this->app->tagged('verification-providers'));
        });

        // The single source of provider ordering for both Find Matches and the cascade.
        $this->app->bind(ProviderScorer::class, TierResponseScorer::class);

        // Runtime tenant context for background work — a singleton so set()/run() persist
        // across app(TenantContext::class) calls within a job. See BelongsToTenant.
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The explicit, greppable "I am reading across tenants on purpose." Drops the tenant
        // global scope so a BearsTenantPhi model can be read cross-tenant without tripping the
        // fail-closed guard (metrics, infra sweeps, super-admin). See BelongsToTenant.
        Builder::macro('crossTenant', fn () => $this->withoutGlobalScope('tenant'));

        FilamentAsset::register([
            Js::make('components-script', __DIR__.'/../../resources/js/components.js'),
        ]);

        // Hide the redundant "View X" page heading on every resource view page across all
        // panels — the record's name already shows in the page content. Breadcrumbs and
        // header actions are untouched. Registered globally (no panel scope) so it applies
        // everywhere at once.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>.fi-resource-view-record-page .fi-header-heading{display:none;}</style>',
        );

        // Breathing room for two cramped icon buttons (scoped to just these two so other
        // filter/notification icons are untouched):
        //  - the provider card-grid filter funnel, which Filament's -8px toolbar-action
        //    margin jams into the corner under the active-filter badge;
        //  - the global header notification bell, which sits flush against the search box
        //    and the user avatar.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>'
                .'.fi-resource-providers .fi-ta-ctn-with-content-layout .fi-ta-filters-dropdown .fi-dropdown-trigger .fi-icon-btn{margin:0 !important;}'
                .'.fi-topbar-database-notifications-btn{margin-inline:0.5rem !important;}'
                .'</style>',
        );
    }
}
