<?php

namespace App\Filament\Dashboard\Resources\ProviderApplications\Pages;

use App\Filament\Dashboard\Resources\ProviderApplications\ProviderApplicationResource;
use App\Models\StaffPick\ProviderApplication;
use App\Services\StaffPick\ProviderApplicationReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProviderApplication extends ViewRecord
{
    protected static string $resource = ProviderApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('Approve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (ProviderApplication $r): bool => $r->status === ProviderApplication::STATUS_SUBMITTED)
                ->requiresConfirmation()
                ->modalHeading(__('Approve this provider application?'))
                ->action(function (ProviderApplication $record): void {
                    try {
                        app(ProviderApplicationReviewService::class)->approve($record, auth()->user());
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Application approved. Invitation sent to :email.', ['email' => $record->email]))
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label(__('Reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (ProviderApplication $r): bool => $r->status === ProviderApplication::STATUS_SUBMITTED)
                ->schema([
                    Textarea::make('rejection_reason')
                        ->label(__('Reason'))
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data, ProviderApplication $record): void {
                    app(ProviderApplicationReviewService::class)->reject($record, auth()->user(), $data['rejection_reason']);

                    Notification::make()
                        ->title(__('Application rejected.'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
