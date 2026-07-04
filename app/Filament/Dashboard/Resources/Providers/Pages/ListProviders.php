<?php

namespace App\Filament\Dashboard\Resources\Providers\Pages;

use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Resources\Providers\Tables\ProvidersTable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Table;

class ListProviders extends ListRecords
{
    protected static string $resource = ProviderResource::class;

    /**
     * Which layout the list renders in. 'grid' (profile cards) is the default; 'list'
     * is the existing Filament table, kept as a user-toggleable alternate. Resets to
     * the card default on each fresh page load, which is the required default view.
     */
    public string $viewLayout = 'grid';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleLayout')
                ->label(fn (): string => $this->viewLayout === 'grid' ? __('Table view') : __('Card view'))
                ->icon(fn (): Heroicon => $this->viewLayout === 'grid' ? Heroicon::OutlinedTableCells : Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->action(function (): void {
                    $this->viewLayout = $this->viewLayout === 'grid' ? 'list' : 'grid';

                    // bootedInteractsWithTable already built $this->table (with the old
                    // layout) before this action ran, so rebuild it now — otherwise the
                    // layout switch would lag one click behind.
                    $this->table = $this->table($this->makeTable());
                }),
            CreateAction::make(),
        ];
    }

    /**
     * Reuse the existing table (filters, actions, sort, list columns) as-is, and in the
     * default 'grid' layout swap the columns for a single profile-card view laid out in
     * a responsive content grid. Global text search only applies in the list layout —
     * the card layout keeps the discipline/tier/status filters.
     */
    public function table(Table $table): Table
    {
        $table = ProvidersTable::configure($table);

        if ($this->viewLayout === 'grid') {
            $table = $table
                ->columns([
                    View::make('staffpick.providers.profile-card'),
                ])
                ->contentGrid(['sm' => 1, 'md' => 2, 'xl' => 3]);
        }

        return $table;
    }
}
