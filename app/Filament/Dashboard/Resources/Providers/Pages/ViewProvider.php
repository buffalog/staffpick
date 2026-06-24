<?php

namespace App\Filament\Dashboard\Resources\Providers\Pages;

use App\Events\StaffPick\ProviderApproved;
use App\Events\StaffPick\ProviderRejected;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Models\StaffPick\Provider;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProvider extends ViewRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === Provider::STATUS_PENDING)
                ->requiresConfirmation()
                ->modalDescription(__('Approve this provider? They will receive a confirmation email.'))
                ->action(function (): void {
                    $this->record->update(['status' => Provider::STATUS_ACTIVE]);

                    ProviderApproved::dispatch($this->record);

                    Notification::make()
                        ->title(__('Provider approved.'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('reject')
                ->label(__('Reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === Provider::STATUS_PENDING)
                ->schema([
                    Select::make('reason')
                        ->label(__('Reason for rejection'))
                        ->required()
                        ->options(Provider::rejectionReasonOptions()),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['status' => Provider::STATUS_REJECTED]);

                    ProviderRejected::dispatch($this->record, $data['reason']);

                    Notification::make()
                        ->title(__('Provider rejected.'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            EditAction::make(),
        ];
    }
}
