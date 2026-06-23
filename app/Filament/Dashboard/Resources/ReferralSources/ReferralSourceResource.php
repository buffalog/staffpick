<?php

namespace App\Filament\Dashboard\Resources\ReferralSources;

use App\Filament\Dashboard\Resources\ReferralSources\Pages\CreateReferralSource;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\EditReferralSource;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\ListReferralSources;
use App\Filament\Dashboard\Resources\ReferralSources\Pages\ViewReferralSource;
use App\Filament\Dashboard\Resources\ReferralSources\Schemas\ReferralSourceForm;
use App\Filament\Dashboard\Resources\ReferralSources\Schemas\ReferralSourceInfolist;
use App\Filament\Dashboard\Resources\ReferralSources\Tables\ReferralSourcesTable;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\ReferralSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferralSourceResource extends Resource
{
    protected static ?string $model = ReferralSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ReferralSourceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReferralSourceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralSourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralSources::route('/'),
            'create' => CreateReferralSource::route('/create'),
            'view' => ViewReferralSource::route('/{record}'),
            'edit' => EditReferralSource::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Cases');
    }

    public static function getModelLabel(): string
    {
        return __('Referral Source');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Referral Sources');
    }

    public static function getNavigationLabel(): string
    {
        return __('Referral Sources');
    }
}
