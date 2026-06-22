<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Support\SpRoleAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Users extends Page
{
    protected string $view = 'filament.dashboard.pages.users';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedUsers;

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    public static function canAccess(): bool
    {
        return config('app.allow_tenant_invitations', false) && SpRoleAccess::isAdmin();
    }
}
