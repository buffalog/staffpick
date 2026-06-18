<?php

namespace App\Filament\Dashboard\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * A compact "?" icon button for a page header that opens the contextual help
 * slide-over to a given doc path ("{role}/{slug}", e.g. "scheduler/dispatch-board").
 * The globally-rendered HelpSlideOver component listens for the dispatched event.
 */
class HelpHeaderAction
{
    public static function make(string $path): Action
    {
        return Action::make('help')
            ->label(__('Help'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->iconButton()
            ->action(fn ($livewire) => $livewire->dispatch('open-help', path: $path));
    }
}
