<?php

namespace App\Filament\Dashboard\Resources\Subjects;

use App\Filament\Dashboard\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Dashboard\Resources\Subjects\Pages\EditSubject;
use App\Filament\Dashboard\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Dashboard\Resources\Subjects\Pages\ViewSubject;
use App\Filament\Dashboard\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\Dashboard\Resources\Subjects\Schemas\SubjectInfolist;
use App\Filament\Dashboard\Resources\Subjects\Tables\SubjectsTable;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\TenantConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    // Patient last name (PHI). Safe ONLY because the dashboard panel ships no analytics —
    // nothing sends document.title to a third party (see DashboardPanelProvider). Subjects
    // have no non-PHI reference number to use instead. If analytics is ever re-added to this
    // (or any PHI-rendering) panel, move this off 'last_name' in the same change.
    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return SubjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubjectsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            // Eager-load the relation the table renders to avoid an N+1 on the list.
            ->with(['insuranceType']);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubjects::route('/'),
            'create' => CreateSubject::route('/create'),
            'view' => ViewSubject::route('/{record}'),
            'edit' => EditSubject::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    // Subjects (patient records) are reached through a case, not browsed directly — hide
    // from the sidebar. (Also removes the now-empty Dispatch group.) Routes stay live.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return TenantConfig::entityLabel('subject', __('Case'));
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
