<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Pages;

use App\Events\StaffPick\ReferralSourceApproved;
use App\Events\StaffPick\ReferralSourceRejected;
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
                ->visible(fn (): bool => $this->record->status === ReferralSource::STATUS_PENDING)
                ->requiresConfirmation()
                ->modalDescription(__('Approve this referral source? They will receive a confirmation email.'))
                ->action(function (): void {
                    $this->record->update(['status' => ReferralSource::STATUS_ACTIVE]);

                    ReferralSourceApproved::dispatch($this->record);

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
                ->visible(fn (): bool => $this->record->status === ReferralSource::STATUS_PENDING)
                ->schema([
                    Select::make('reason')
                        ->label(__('Reason for rejection'))
                        ->required()
                        ->options(ReferralSource::rejectionReasonOptions()),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['status' => ReferralSource::STATUS_REJECTED]);

                    ReferralSourceRejected::dispatch($this->record, $data['reason']);

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
