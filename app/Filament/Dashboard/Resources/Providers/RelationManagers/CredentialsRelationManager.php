<?php

namespace App\Filament\Dashboard\Resources\Providers\RelationManagers;

use App\Filament\Dashboard\Credentialing\ManualCredential;
use App\Filament\Dashboard\Credentialing\VerifyCredentialAction;
use App\Models\StaffPick\ProviderCredential;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Credentials shown on the provider view page, each with its verification status and a
 * per-credential Verify action (same logic as the Credentialing Queue).
 */
class CredentialsRelationManager extends RelationManager
{
    protected static string $relationship = 'credentials';

    public function table(Table $table): Table
    {
        return $table
            // No heading — this table is embedded inside the "Credentials" accordion, whose
            // header (with the alert dot) replaces it.
            ->heading('')
            // Eager-load documentType so the Credential column doesn't N+1 per row, and
            // apply the scheduler-visibility gate — sp_staff never fetches HR-only rows.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('documentType')->visibleToCurrentUser())
            ->columns([
                TextColumn::make('documentType.name')->label(__('Credential')),
                TextColumn::make('license_number')->label(__('License #'))->placeholder('—'),
                TextColumn::make('verification_status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        ProviderCredential::VERIFICATION_VERIFIED => 'success',
                        ProviderCredential::VERIFICATION_FAILED => 'danger',
                        ProviderCredential::VERIFICATION_PENDING, ProviderCredential::VERIFICATION_PENDING_MANUAL => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')->label(__('Expires'))->date()->placeholder('—'),
                TextColumn::make('last_verified_at')->label(__('Last verified'))->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                Action::make('addCredential')
                    ->label(__('Add Credential'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading(__('Add Credential'))
                    ->modalSubmitActionLabel(__('Add'))
                    ->schema(ManualCredential::fields((int) $this->getOwnerRecord()->tenant_id))
                    ->action(function (array $data): void {
                        $provider = $this->getOwnerRecord();

                        ManualCredential::create($data, (int) $provider->id, (int) $provider->tenant_id);

                        Notification::make()
                            ->title(__('Credential added'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('attachments')
                    ->label(__('Attachments'))
                    ->icon(Heroicon::OutlinedPaperClip)
                    // Live count of non-tombstoned attachments; hidden when there are none.
                    ->badge(fn (ProviderCredential $record): ?int => ($count = $record->attachments()->count()) > 0 ? $count : null)
                    // Same visible_to_scheduler gate as viewing/verifying the credential.
                    ->visible(fn (ProviderCredential $record): bool => $record->isAccessibleBy(auth()->user()))
                    ->modalHeading(fn (ProviderCredential $record): string => __('Attachments — :type', ['type' => $record->documentType?->name]))
                    ->modalContent(fn (ProviderCredential $record) => view('staffpick.credentials.attachments-modal', ['credentialId' => $record->id]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                VerifyCredentialAction::make(),
            ]);
    }
}
