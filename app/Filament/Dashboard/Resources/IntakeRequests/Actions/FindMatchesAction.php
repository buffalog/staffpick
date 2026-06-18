<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Models\StaffPick\IntakeRequest;
use App\Services\StaffPick\MatchingEngine;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * "Find Matches" — runs the matching engine for an intake request and presents the
 * ranked, eligible providers in a read-only modal table. Reusable across the table
 * row actions and the view-page header.
 */
class FindMatchesAction
{
    public static function make(): Action
    {
        return Action::make('findMatches')
            ->label(__('Find Matches'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->modalHeading(fn (IntakeRequest $record): string => __('Provider matches for :reference', [
                'reference' => $record->reference_number ?: "#{$record->id}",
            ]))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->extraModalFooterActions([
                Action::make('matchingHelp')
                    ->label(__('How matching works'))
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->color('gray')
                    ->link()
                    ->action(fn ($livewire) => $livewire->dispatch('open-help', path: 'scheduler/running-the-matching-engine')),
            ])
            ->modalContent(fn (IntakeRequest $record) => view('staffpick.intake-requests.matches', [
                'record' => $record,
                'results' => app(MatchingEngine::class)->match($record),
            ]));
    }
}
