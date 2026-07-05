<?php

namespace App\Filament\Dashboard\Resources\Providers;

use App\Filament\Dashboard\Resources\Providers\Pages\CreateProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\EditProvider;
use App\Filament\Dashboard\Resources\Providers\Pages\ListProviders;
use App\Filament\Dashboard\Resources\Providers\Pages\ViewProvider;
use App\Filament\Dashboard\Resources\Providers\Schemas\ProviderForm;
use App\Filament\Dashboard\Resources\Providers\Schemas\ProviderInfolist;
use App\Filament\Dashboard\Resources\Providers\Tables\ProvidersTable;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderApplication;
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

    protected static ?int $navigationSort = 1;

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
            // Eager-load the relationships the list/card views render (discipline, tier,
            // languages) to avoid an N+1 across the rows.
            ->with(['discipline', 'disciplines', 'tier', 'languages'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        // Credentials is embedded inside the detail-page accordions (provider-accordions
        // blade) so it reads as a flat section; it is NOT rendered here as a separate
        // relation-manager block, which would double it up.
        return [];
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

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Our Providers');
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
        return __('Provider List');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ProviderApplication::query()
            ->where('status', ProviderApplication::STATUS_SUBMITTED)
            ->count();

        return $count > 0 ? (string) $count : null;
    }
}
