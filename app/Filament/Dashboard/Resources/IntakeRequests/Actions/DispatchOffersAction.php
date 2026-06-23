<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Jobs\StaffPick\DispatchOffers;
use App\Models\StaffPick\IntakeRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * "Auto Dispatch" — full-auto pipeline: builds the ranked provider queue and fires
 * sequential offers to all eligible providers with no staff review (no modal). The
 * semi-auto, review-each-provider path lives in the Find Matches modal.
 */
class DispatchOffersAction
{
    public static function make(): Action
    {
        return Action::make('dispatchOffers')
            ->label(__('Auto Dispatch'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (IntakeRequest $record): bool => in_array($record->status, ['pending', 'matching'], true))
            ->action(function (IntakeRequest $record): void {
                abort_unless(Gate::allows('update', $record), 403);

                DispatchOffers::dispatch($record->id);

                Notification::make()
                    ->title(__('Offers dispatching'))
                    ->body(__('Providers are being offered this case in ranked order.'))
                    ->success()
                    ->send();
            });
    }
}
