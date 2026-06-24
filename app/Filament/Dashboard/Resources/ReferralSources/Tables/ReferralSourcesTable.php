<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Tables;

use App\Events\StaffPick\ReferralSourceApproved;
use App\Events\StaffPick\ReferralSourceRejected;
use App\Models\StaffPick\ReferralSource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReferralSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load the group the table renders to avoid an N+1 on the list.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('group'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group.name')
                    ->label(__('Group'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('city')
                    ->label(__('City'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('state')
                    ->label(__('State'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'delinquent' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label(__('Group'))
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Active'),
                        'inactive' => __('Inactive'),
                        'delinquent' => __('Delinquent'),
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReferralSource $record): bool => $record->status === ReferralSource::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalDescription(__('Approve this referral source? They will receive a confirmation email.'))
                    ->action(function (ReferralSource $record): void {
                        $record->update(['status' => ReferralSource::STATUS_ACTIVE]);

                        ReferralSourceApproved::dispatch($record);

                        Notification::make()
                            ->title(__('Referral source approved.'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ReferralSource $record): bool => $record->status === ReferralSource::STATUS_PENDING)
                    ->schema([
                        Select::make('reason')
                            ->label(__('Reason for rejection'))
                            ->required()
                            ->options(ReferralSource::rejectionReasonOptions()),
                    ])
                    ->action(function (array $data, ReferralSource $record): void {
                        $record->update(['status' => ReferralSource::STATUS_REJECTED]);

                        ReferralSourceRejected::dispatch($record, $data['reason']);

                        Notification::make()
                            ->title(__('Referral source rejected.'))
                            ->success()
                            ->send();
                    }),
                Action::make('intakeLink')
                    ->label(__('Intake link'))
                    ->icon(Heroicon::OutlinedLink)
                    ->color('gray')
                    ->fillForm(fn (ReferralSource $record): array => ['intake_url' => $record->getIntakeUrl()])
                    ->schema([
                        TextInput::make('intake_url')
                            ->label(__('Public intake link'))
                            ->readOnly()
                            ->helperText(__('Share this link with the referral source so they can submit referrals directly.')),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
