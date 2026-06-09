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
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
            ]);
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
        return __('StaffPick');
    }

    public static function getModelLabel(): string
    {
        return __('Subject');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Subjects');
    }

    public static function getNavigationLabel(): string
    {
        return __('Subjects');
    }
}
