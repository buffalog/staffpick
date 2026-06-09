<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CreateIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\EditIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ListIntakeRequests;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Schemas\IntakeRequestForm;
use App\Filament\Dashboard\Resources\IntakeRequests\Schemas\IntakeRequestInfolist;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Models\StaffPick\IntakeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IntakeRequestResource extends Resource
{
    protected static ?string $model = IntakeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'reference_number';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'pending' => __('Pending'),
            'matching' => __('Matching'),
            'assigned_pending' => __('Assigned (pending)'),
            'active' => __('Active'),
            'on_hold' => __('On hold'),
            'finished' => __('Finished'),
            'cancelled' => __('Cancelled'),
            'closed' => __('Closed'),
        ];
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'active', 'finished' => 'success',
            'matching' => 'info',
            'assigned_pending', 'on_hold' => 'warning',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        return IntakeRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IntakeRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntakeRequestsTable::configure($table);
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
            'index' => ListIntakeRequests::route('/'),
            'create' => CreateIntakeRequest::route('/create'),
            'view' => ViewIntakeRequest::route('/{record}'),
            'edit' => EditIntakeRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('StaffPick');
    }

    public static function getModelLabel(): string
    {
        return __('Intake Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Intake Requests');
    }

    public static function getNavigationLabel(): string
    {
        return __('Intake Requests');
    }
}
