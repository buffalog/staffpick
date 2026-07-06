<?php

namespace App\Filament\Dashboard\Credentialing;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\ProviderCredential;
use App\Services\StaffPick\CredentialComplianceService;
use App\Services\StaffPick\Credentialing\LicenseVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Reusable "Verify" action for a ProviderCredential, shared by the Credentialing Queue
 * page and the provider Credentials relation manager. Branches on the credential type's
 * verification method:
 *  - api       — runs LicenseVerificationService and shows the mapped result inline.
 *  - deep_link — marks the credential pending_manual_confirmation and offers a button
 *                that opens the pre-filled state-board URL in a new tab.
 *  - manual    — opens a modal to mark verified/failed with notes.
 *
 * Authorization is scoped per credential type via ProviderCredential::isVerifiableByCurrentUser
 * (verifiable iff visible): admin/HR/super-admin verify any type; sp_staff verify only
 * types flagged visible_to_scheduler. The action is hidden on rows the user can't verify,
 * and the handler re-checks so a denied request gets a clear message rather than a raw 403.
 */
class VerifyCredentialAction
{
    public static function make(): Action
    {
        return Action::make('verifyCredential')
            ->label(__('Verify Now'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('primary')
            ->visible(fn (ProviderCredential $record): bool => $record->isVerifiableByCurrentUser())
            ->modalHeading(__('Mark credential verification'))
            ->schema(fn (ProviderCredential $record): array => $record->documentType?->verification_method === CredentialDocumentType::METHOD_MANUAL
                ? [
                    Radio::make('result')
                        ->label(__('Outcome'))
                        ->options([
                            ProviderCredential::VERIFICATION_VERIFIED => __('Verified'),
                            ProviderCredential::VERIFICATION_FAILED => __('Failed'),
                        ])
                        ->required(),
                    Textarea::make('notes')->label(__('Notes'))->rows(2),
                ]
                : [])
            ->action(function (ProviderCredential $record, array $data): void {
                // Re-check server-side (the ->visible() gate hides the button, but a forged
                // Livewire call must still be refused). Denied = a clear, non-retryable
                // message rather than a raw 403 that surfaces as a generic "try again" toast.
                if (! $record->isVerifiableByCurrentUser()) {
                    Notification::make()
                        ->title(__('Insufficient permission to verify this credential type'))
                        ->danger()
                        ->send();

                    return;
                }

                $method = $record->documentType?->verification_method;

                if ($method === CredentialDocumentType::METHOD_MANUAL) {
                    $record->update([
                        'verification_status' => $data['result'],
                        'verification_source' => ProviderCredential::SOURCE_MANUAL,
                        'last_verified_at' => now(),
                        'verified_by_user_id' => auth()->id(),
                        'notes' => $data['notes'] ?? $record->notes,
                    ]);

                    if ($data['result'] === ProviderCredential::VERIFICATION_VERIFIED && $record->provider !== null) {
                        app(CredentialComplianceService::class)->reactivateIfEligible($record->provider);
                    }

                    Notification::make()->title(__('Credential marked :status', ['status' => $data['result']]))->success()->send();

                    return;
                }

                $result = app(LicenseVerificationService::class)->verify($record);

                if ($method === CredentialDocumentType::METHOD_DEEP_LINK) {
                    $record->update([
                        'verification_status' => ProviderCredential::VERIFICATION_PENDING_MANUAL,
                        'verification_source' => ProviderCredential::SOURCE_DEEP_LINK,
                    ]);

                    Notification::make()
                        ->title(__('Open the licensing board to verify'))
                        ->body(__('Marked pending. Open the board in the new tab, confirm the license, then mark it verified.'))
                        ->actions([
                            Action::make('openBoard')->label(__('Open licensing board'))->url($result->deepLinkUrl, shouldOpenInNewTab: true),
                        ])
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                // api
                if ($result->status === ProviderCredential::VERIFICATION_VERIFIED) {
                    if ($record->provider !== null) {
                        app(CredentialComplianceService::class)->reactivateIfEligible($record->provider);
                    }

                    Notification::make()->title(__('License verified'))->success()->send();
                } else {
                    Notification::make()->title(__('License verification failed'))->body(__('The licensing board did not return an active license.'))->danger()->send();
                }
            });
    }
}
