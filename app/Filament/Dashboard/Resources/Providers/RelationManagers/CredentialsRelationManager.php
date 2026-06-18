<?php

namespace App\Filament\Dashboard\Resources\Providers\RelationManagers;

use App\Filament\Dashboard\Credentialing\VerifyCredentialAction;
use App\Models\StaffPick\ProviderCredential;
use Filament\Resources\RelationManagers\RelationManager;
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

    protected static ?string $title = 'Credentials';

    public function table(Table $table): Table
    {
        return $table
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
            ->recordActions([
                VerifyCredentialAction::make(),
            ]);
    }
}
