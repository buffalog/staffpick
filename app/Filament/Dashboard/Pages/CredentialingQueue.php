<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Credentialing\VerifyCredentialAction;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\ProviderCredential;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Credentialing queue: every provider credential across the tenant that needs
 * attention — unverified, failed, or expiring within 30 days — with inline
 * verification per the credential type's method. Gated to tenant admins.
 */
class CredentialingQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $slug = 'credentialing';

    protected string $view = 'filament.dashboard.pages.credentialing-queue';

    public function getTitle(): string|Htmlable
    {
        return __('Credentialing');
    }

    public static function getNavigationLabel(): string
    {
        return __('Credentialing');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Credentialing');
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProviderCredential::query()
                    ->with(['provider', 'documentType'])
                    ->whereHas('provider', fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()?->id))
                    ->where(function (Builder $query) {
                        $query->whereIn('verification_status', [
                            ProviderCredential::VERIFICATION_UNVERIFIED,
                            ProviderCredential::VERIFICATION_FAILED,
                        ])->orWhere(fn (Builder $sub) => $sub
                            ->whereNotNull('expires_at')
                            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]));
                    })
            )
            ->columns([
                TextColumn::make('provider_name')
                    ->label(__('Provider'))
                    ->state(fn (ProviderCredential $record): string => trim("{$record->provider?->first_name} {$record->provider?->last_name}")),
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
                TextColumn::make('expires_at')->label(__('Expires'))->date()->placeholder('—')->sortable(),
                TextColumn::make('last_verified_at')->label(__('Last verified'))->dateTime()->placeholder('—'),
                TextColumn::make('documentType.verification_method')
                    ->label(__('Method'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'api' => 'info',
                        'deep_link' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('verification_status')
                    ->label(__('Status'))
                    ->options([
                        ProviderCredential::VERIFICATION_UNVERIFIED => __('Unverified'),
                        ProviderCredential::VERIFICATION_VERIFIED => __('Verified'),
                        ProviderCredential::VERIFICATION_FAILED => __('Failed'),
                        ProviderCredential::VERIFICATION_PENDING => __('Pending'),
                        ProviderCredential::VERIFICATION_PENDING_MANUAL => __('Pending manual confirmation'),
                    ]),
                SelectFilter::make('document_type_id')
                    ->label(__('Credential type'))
                    ->relationship('documentType', 'name'),
                Filter::make('expiring_soon')
                    ->label(__('Expiring within 30 days'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('expires_at')
                        ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])),
            ])
            ->recordActions([
                VerifyCredentialAction::make(),
                Action::make('viewProvider')
                    ->label(__('View Provider'))
                    ->icon(Heroicon::OutlinedUser)
                    ->color('gray')
                    ->url(fn (ProviderCredential $record): string => ProviderResource::getUrl('view', ['record' => $record->provider_id])),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [HelpHeaderAction::make('scheduler/credentialing')];
    }
}
