<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Table;

/**
 * All Cases — every intake request across all statuses for this tenant.
 */
class AllCases extends ListRecords
{
    protected static string $resource = IntakeRequestResource::class;

    /**
     * Which layout the list renders in. 'grid' (case cards) is the default; 'list' is the
     * Filament table, kept as a user-toggleable alternate. Resets to the card default on each
     * fresh page load. Mirrors the Providers list toggle.
     */
    public string $viewLayout = 'grid';

    public function getTitle(): string
    {
        return __('All Cases');
    }

    /**
     * Reuse the shared cases table (filters, actions, sort, list columns) as-is, and in the
     * default 'grid' layout swap the columns for a single case-card view laid out in a
     * responsive content grid. The card column isn't searchable, so re-declare the same search
     * fields at the table level — reference_number (direct) and subject.last_name (dot notation
     * resolves the relationship) — to keep the global search box working in the grid layout.
     */
    public function table(Table $table): Table
    {
        $table = IntakeRequestsTable::configure($table, withDispatchActions: false);

        if ($this->viewLayout === 'grid') {
            $table = $table
                ->columns([
                    View::make('staffpick.intake-requests.case-card'),
                ])
                ->contentGrid(['sm' => 1, 'md' => 2, 'xl' => 3])
                ->searchable(['reference_number', 'subject.last_name']);
        }

        return $table;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleLayout')
                ->label(fn (): string => $this->viewLayout === 'grid' ? __('Table view') : __('Card view'))
                ->icon(fn (): Heroicon => $this->viewLayout === 'grid' ? Heroicon::OutlinedTableCells : Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->action(function (): void {
                    $this->viewLayout = $this->viewLayout === 'grid' ? 'list' : 'grid';

                    // bootedInteractsWithTable already built $this->table (with the old layout)
                    // before this action ran, so rebuild it now — otherwise the layout switch
                    // would lag one click behind.
                    $this->table = $this->table($this->makeTable());
                }),
            HelpHeaderAction::make('scheduler/managing-intake-requests'),
        ];
    }
}
