<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * All Cases — the full operational picture minus the dispatch queue. Excludes pending /
 * offered / assigned_pending (those live in Pending Cases). 'finished' = completed.
 */
class AllCases extends ListRecords
{
    protected static string $resource = IntakeRequestResource::class;

    public const STATUSES = ['active', 'completed', 'finished', 'cancelled', 'on_hold'];

    public function getTitle(): string
    {
        return __('All Cases');
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
