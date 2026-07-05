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
use Illuminate\Support\HtmlString;

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
            // Heading carries a red dot when any credential is expired/expiring soon — the
            // same threshold as the compliance sweep (Provider::credentialAlertCount()).
            ->heading(fn (): HtmlString => new HtmlString(
                e(__('Credentials')).($this->getOwnerRecord()->credentialAlertCount() > 0
                    ? ' <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500 align-middle" title="'.e(__('A credential is expired or expiring soon')).'"></span>'
                    : '')
            ))
            // Eager-load documentType so the Credential column doesn't N+1 per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('documentType'))
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
                VerifyCredentialAction::make(),
            ]);
    }
}
