<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Support\SpRoleAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Service Calendar page — hosts the ServiceCalendarWidget (provider-row timeline of
 * scheduled active cases). Replaces the dispatch board in the top navigation slot.
 * Gated to tenant admins/staff (PHI), same as the board it replaces.
 */
class ServiceCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $slug = 'service-calendar';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.dashboard.pages.service-calendar';

    public static function getNavigationLabel(): string
    {
        return __('Service Calendar');
    }

    public function getTitle(): string
    {
        return __('Service Calendar');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }
}
