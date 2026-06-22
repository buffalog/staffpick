<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BladeProvider;
use App\Providers\ConfigProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\DashboardPanelProvider;
use App\Providers\Filament\ProviderPanelProvider;
use App\Providers\Filament\ReferrerPanelProvider;
use App\Providers\Filament\SuperAdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RouteServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

return [
    ConfigProvider::class,
    BladeProvider::class,
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminPanelProvider::class,
    DashboardPanelProvider::class,
    ProviderPanelProvider::class,
    ReferrerPanelProvider::class,
    SuperAdminPanelProvider::class,
    HorizonServiceProvider::class,
    RouteServiceProvider::class,
    PermissionServiceProvider::class,
];
