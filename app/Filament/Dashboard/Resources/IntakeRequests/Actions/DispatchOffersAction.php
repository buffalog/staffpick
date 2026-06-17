<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Jobs\StaffPick\DispatchOffers;
use App\Models\StaffPick\IntakeRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * "Dispatch Offers" — starts the assignment-offer pipeline for an intake: builds the
 * ranked queue and sends the first offer. Shown while the case is awaiting matching.
 */
class DispatchOffersAction
{
    public static function make(): Action
    {
        return Action::make('dispatchOffers')
            ->label(__('Dispatch Offers'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (IntakeRequest $record): bool => in_array($record->status, ['pending', 'matching'], true))
            ->requiresConfirmation()
            ->modalDescription(__('Build the ranked provider queue and start sending offers one at a time.'))
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
