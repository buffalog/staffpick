<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\IntakeRequest;
use App\Services\StaffPick\MatchDispatchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * "Auto Dispatch" — kicks off the match cascade: offers the case to the best available
 * provider; timeouts/declines cascade automatically, escalating if the pool is exhausted.
 * The semi-auto, review-each-provider path lives in the Find Matches modal.
 */
class DispatchOffersAction
{
    public static function make(): Action
    {
        return Action::make('dispatchOffers')
            ->label(__('Auto Dispatch'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (IntakeRequest $record): bool => $record->status === 'unmatched')
            ->action(function (IntakeRequest $record): void {
                abort_unless(SpRoleAccess::isAdminOrStaff(), 403);

                app(MatchDispatchService::class)->dispatch($record);

                Notification::make()
                    ->title(__('Match dispatched'))
                    ->body(__('The best available provider has been offered this case.'))
                    ->success()
                    ->send();
            });
    }
}
