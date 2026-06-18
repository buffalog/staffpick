<?php

namespace App\Filament\SuperAdmin\Resources\SuperAdmins;

use App\Filament\SuperAdmin\Resources\SuperAdmins\Pages\ListSuperAdmins;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only roster of platform super admins. Deliberately has no create/edit/delete —
 * super-admin status is granted only via the staffpick:create-super-admin command or
 * direct DB access, never the UI.
 */
class SuperAdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 90;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_super_admin', true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('Email'))->searchable()->sortable(),
                TextColumn::make('last_seen_at')->label(__('Last seen'))->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(__('Created'))->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuperAdmins::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Super Admins');
    }

    public static function getModelLabel(): string
    {
        return __('Super Admin');
    }
}
