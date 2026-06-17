<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Jobs\StaffPick\DispatchOffers;
use App\Models\StaffPick\IntakeRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * "Re-trigger Matching" — shown once an intake's queue is exhausted
 * (no_clinicians_available). Re-runs the engine with a one-time expanded radius for
 * this intake only (no change to tenant defaults or provider preferences) and
 * rebuilds the offer queue.
 */
class RetriggerMatchingAction
{
    public static function make(): Action
    {
        return Action::make('retriggerMatching')
            ->label(__('Re-trigger Matching'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (IntakeRequest $record): bool => $record->status === 'no_clinicians_available')
            ->schema([
                TextInput::make('radius_override')
                    ->label(__('Expanded radius (miles)'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(500)
                    ->required()
                    ->helperText(__('One-time for this intake only — does not change tenant defaults or provider preferences.')),
            ])
            ->action(function (array $data, IntakeRequest $record): void {
                abort_unless(Gate::allows('update', $record), 403);

                DispatchOffers::dispatch($record->id, (float) $data['radius_override']);

                Notification::make()
                    ->title(__('Matching re-triggered'))
                    ->body(__('Re-running with an expanded radius of :miles miles.', ['miles' => (int) $data['radius_override']]))
                    ->success()
                    ->send();
            });
    }
}
