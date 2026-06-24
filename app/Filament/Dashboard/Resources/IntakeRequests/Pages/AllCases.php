<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

/**
 * All Cases — every intake request across all statuses for this tenant.
 */
class AllCases extends ListRecords
{
    protected static string $resource = IntakeRequestResource::class;

    public function getTitle(): string
    {
        return __('All Cases');
    }

    public function table(Table $table): Table
    {
        return IntakeRequestsTable::configure($table, withDispatchActions: false);
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpHeaderAction::make('scheduler/managing-intake-requests'),
        ];
    }
}
