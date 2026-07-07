<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Services\StaffPick\MatchingEngine;
use App\Services\StaffPick\MatchingResult;
use App\Services\StaffPick\ProviderScorer;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

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
                'results' => self::orderedResults($record),
                // Providers with a live (pending) offer already — recomputed each render
                // so a just-dispatched offer immediately shows as "Offer Sent".
                'alreadyOfferedProviderIds' => $record->assignmentOffers()
                    ->where('status', AssignmentOffer::STATUS_PENDING)
                    ->pluck('provider_id')
                    ->all(),
            ]));
    }

    /**
     * Eligible providers from {@see MatchingEngine} (unsorted), reordered by the single
     * {@see ProviderScorer} used by Auto Dispatch. The modal MUST show the exact order the
     * cascade would offer in — so both paths agree — so we score the eligible providers and
     * re-associate that order back onto their MatchingResults.
     *
     * @return Collection<int, MatchingResult>
     */
    private static function orderedResults(IntakeRequest $record): Collection
    {
        $results = app(MatchingEngine::class)->match($record);
        $resultByProviderId = $results->keyBy(fn (MatchingResult $result): int => $result->provider->id);

        return app(ProviderScorer::class)
            ->order($record, $results->map(fn (MatchingResult $result): Provider => $result->provider))
            ->map(fn (Provider $provider): ?MatchingResult => $resultByProviderId->get($provider->id))
            ->filter()
            ->values();
    }

    /** @var array<int, bool> */
    private static array $assignmentCache = [];

    private static function hasActiveAssignment(IntakeRequest $record): bool
    {
        if (! isset(self::$assignmentCache[$record->id])) {
            self::$assignmentCache[$record->id] = $record->assignments()
                ->where('status', Assignment::STATUS_ACTIVE)
                ->exists();
        }

        return self::$assignmentCache[$record->id];
    }
}
