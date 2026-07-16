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
            // languages) to avoid an N+1 across the rows. The photo relation is loaded
            // metadata-only (never the BLOB) so the avatar can build its versioned URL.
            ->with([
                'discipline', 'disciplines', 'tier', 'languages',
                'photo' => fn ($query) => $query->select(['id', 'provider_id', 'updated_at']),
            ])
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
        // Editing is field-scoped in the form; sp_staff, sp_hr, sp_admin, and super-admin
        // may all reach the resource.
        return SpRoleAccess::canEditProviders();
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

    /**
     * Derived from the model label, NOT hardcoded — a tenant that renames Provider to
     * Clinician must see "Clinicians" in the sidebar, not a fixed string. (A nav-only
     * refactor once inlined __('Provider List') here and silently un-wired the
     * tenant-configurable label for this item.)
     */
    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * Providers awaiting credentialing/activation (status = pending), tenant-scoped. This is the
     * onboarding backlog: it ticks down as staff credential and activate each provider. NOT a
     * count of submitted applications, which was the prior bug (this badge queried
     * ProviderApplication, so "Providers [N]" meant N applications, never the provider roster).
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Provider::query()
            ->where('status', Provider::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
