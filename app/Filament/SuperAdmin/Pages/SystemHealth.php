<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

/**
 * Platform health overview — the super-admin panel's landing page. Counts span every
 * tenant (the sp_* global scope is bypassed so we see the whole install).
 */
class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $slug = 'system-health';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.super-admin.pages.system-health';

    public function getTitle(): string|Htmlable
    {
        return __('System Health');
    }

    public static function getNavigationLabel(): string
    {
        return __('System Health');
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    public function stats(): array
    {
        return [
            ['label' => __('Tenants'), 'value' => Tenant::count()],
            ['label' => __('Users'), 'value' => User::count()],
            ['label' => __('Super Admins'), 'value' => User::where('is_super_admin', true)->count()],
            ['label' => __('Providers'), 'value' => DB::table('sp_providers')->whereNull('deleted_at')->count()],
            ['label' => __('Intake Requests'), 'value' => DB::table('sp_intake_requests')->whereNull('deleted_at')->count()],
        ];
    }
}
