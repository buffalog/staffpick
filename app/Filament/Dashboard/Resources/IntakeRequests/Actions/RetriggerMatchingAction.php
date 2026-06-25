<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Actions;

use App\Models\StaffPick\IntakeRequest;
use App\Services\StaffPick\MatchDispatchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * "Force Match" — shown on an ESCALATED case (provider pool exhausted). Re-runs the
 * cascade with relaxed eligibility ($forceMatch) to reach providers outside the normal
 * pool. The relaxation is currently a geo-only stub; full criteria-relaxation rules
 * (which must never silently drop clinical-safety filters) come in a later spec.
 */
class RetriggerMatchingAction
{
    public static function make(): Action
    {
        return Action::make('retriggerMatching')
            ->label(__('Force Match'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (IntakeRequest $record): bool => $record->status === 'escalated')
            ->requiresConfirmation()
            ->modalDescription(__('Re-runs matching with relaxed criteria to reach providers outside the normal eligible pool.'))
            ->action(function (IntakeRequest $record): void {
                abort_unless(Gate::allows('update', $record), 403);

                app(MatchDispatchService::class)->dispatch($record, forceMatch: true);

                Notification::make()
                    ->title(__('Force match re-triggered'))
                    ->body(__('Re-running with relaxed eligibility.'))
                    ->success()
                    ->send();
            });
    }
}
