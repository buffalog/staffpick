<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Pages;

use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use App\Models\StaffPick\ReferralSource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReferralSource extends ViewRecord
{
    protected static string $resource = ReferralSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->isPendingApproval())
                ->requiresConfirmation()
                ->modalDescription(__('Approve this referral source? They will receive a confirmation email.'))
                ->action(function (): void {
                    $this->record->approve();

                    Notification::make()
                        ->title(__('Referral source approved.'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('reject')
                ->label(__('Reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->isPendingApproval())
                ->schema([
                    Select::make('reason')
                        ->label(__('Reason for rejection'))
                        ->required()
                        ->options(ReferralSource::rejectionReasonOptions()),
                ])
                ->action(function (array $data): void {
                    $this->record->reject($data['reason']);

                    Notification::make()
                        ->title(__('Referral source rejected.'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            EditAction::make(),
        ];
    }
}
