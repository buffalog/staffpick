<?php

namespace App\Filament\Dashboard\Resources\Subjects;

use App\Filament\Dashboard\Resources\Subjects\Pages\CreateSubject;
use App\Filament\Dashboard\Resources\Subjects\Pages\EditSubject;
use App\Filament\Dashboard\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Dashboard\Resources\Subjects\Pages\ViewSubject;
use App\Filament\Dashboard\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\Dashboard\Resources\Subjects\Schemas\SubjectInfolist;
use App\Filament\Dashboard\Resources\Subjects\Tables\SubjectsTable;
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

    public static function getNavigationGroup(): ?string
    {
        return __('Dispatch');
    }

    public static function getModelLabel(): string
    {
        return TenantConfig::entityLabel('subject', __('Subject'));
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
