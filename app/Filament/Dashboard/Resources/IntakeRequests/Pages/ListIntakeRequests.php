<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\Concerns\AssignsMatchedProviders;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pending Cases — the dispatch queue: cases awaiting a clinician. The only scoped
 * view with the Find Matches / Auto Dispatch / Re-trigger row actions.
 */
class ListIntakeRequests extends ListRecords
{
    use AssignsMatchedProviders;

    protected static string $resource = IntakeRequestResource::class;

    /** In-flight statuses shown in the dispatch queue (pre-matched). */
    public const STATUSES = ['unmatched', 'match_sent', 'escalated'];

    public function getTitle(): string
    {
        return __('Pending Cases');
    }

    public function table(Table $table): Table
    {
        return IntakeRequestsTable::configure($table, withDispatchActions: true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', self::STATUSES));
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpHeaderAction::make('scheduler/managing-intake-requests'),
        ];
    }
}
