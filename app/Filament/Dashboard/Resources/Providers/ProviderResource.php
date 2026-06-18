<?php

namespace App\Filament\Dashboard\Resources\Providers;

use App\Filament\Dashboard\Resources\Providers\Pages\CreateProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\EditProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\ListProviders;
use App\Filament\Dashboard\Resources\Providers\Pages\ViewProvider;
use App\Filament\Dashboard\Resources\Providers\RelationManagers\CredentialsRelationManager;
use App\Filament\Dashboard\Resources\Providers\Schemas\ProviderForm;
use App\Filament\Dashboard\Resources\Providers\Schemas\ProviderInfolist;
use App\Filament\Dashboard\Resources\Providers\Tables\ProvidersTable;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return ProviderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProviderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvidersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Eager-load the relationships the list table renders (discipline / tier)
            // to avoid an N+1 across the rows.
            ->with(['discipline', 'tier'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CredentialsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'view' => ViewProvider::route('/{record}'),
            'edit' => EditProvider::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('StaffPick');
    }

    public static function getModelLabel(): string
    {
        return TenantConfig::entityLabel('provider', __('Provider'));
    }

    public static function getPluralModelLabel(): string
    {
        return Str::plural(static::getModelLabel());
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }
}
