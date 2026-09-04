<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Concerns\LogsTableRecordList;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use App\Models\StaffPick\IntakeRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Discharged Cases — read-only history: completed, cancelled and on-hold cases.
 */
class CompletedCases extends ListRecords
{
    use LogsTableRecordList;

    protected static string $resource = IntakeRequestResource::class;

    // 'finished' was listed here until now. It belonged to the pre-2026-06-25 vocabulary and
    // has no forward mapping in the remap migration, so no row can hold it (confirmed zero on
    // staging). Listing a value the model does not define is the same defect as writing one.
    public const STATUSES = [
        IntakeRequest::STATUS_COMPLETED,
        IntakeRequest::STATUS_CANCELLED,
        IntakeRequest::STATUS_ON_HOLD,
    ];

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
