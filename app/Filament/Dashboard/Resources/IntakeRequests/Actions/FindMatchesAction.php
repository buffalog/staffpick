<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
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
            // No re-matching a case that's already actively assigned.
            ->disabled(fn (IntakeRequest $record): bool => self::hasActiveAssignment($record))
            ->tooltip(fn (IntakeRequest $record): ?string => self::hasActiveAssignment($record)
                ? __('This case already has an active assignment.')
                : null)
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
                // Providers with a live (pending) offer already — recomputed each render
                // so a just-dispatched offer immediately shows as "Offer Sent".
                'alreadyOfferedProviderIds' => $record->assignmentOffers()
                    ->where('status', AssignmentOffer::STATUS_PENDING)
                    ->pluck('provider_id')
                    ->all(),
            ]));
    }

    private static function hasActiveAssignment(IntakeRequest $record): bool
    {
        return $record->assignments()
            ->where('status', Assignment::STATUS_ACTIVE)
            ->exists();
    }
}
