<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Discharged Cases — read-only history. 'finished' is treated as discharged.
 */
class CompletedCases extends ListRecords
{
    protected static string $resource = IntakeRequestResource::class;

    public const STATUSES = ['completed', 'finished', 'cancelled', 'on_hold'];

    public function getTitle(): string
    {
        return __('Discharged Cases');
    }

    public function table(Table $table): Table
    {
        return IntakeRequestsTable::configure($table, withDispatchActions: false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', self::STATUSES));
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpHeaderAction::make('scheduler/managing-intake-requests'),
        ];
    }
}
