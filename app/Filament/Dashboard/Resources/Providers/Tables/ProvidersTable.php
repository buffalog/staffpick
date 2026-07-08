<?php

namespace App\Filament\Dashboard\Resources\Providers\Tables;

use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use App\Services\StaffPick\ProviderProfileService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Name'))
                    ->state(fn (Provider $record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->description(fn (Provider $record): ?string => $record->business_name)
                    ->searchable(['first_name', 'last_name', 'business_name'])
                    ->sortable(['last_name']),
                TextColumn::make('disciplines.name')
                    ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('tier.name')
                    ->label(__('Tier'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('radius_preferred_miles')
                    ->label(__('Radius'))
                    ->suffix(__(' mi'))
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('disciplines')
                    ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                    ->relationship('disciplines', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('tier')
                    ->label(__('Tier'))
                    ->relationship('tier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Active'),
                        'inactive' => __('Inactive'),
                        'pending' => __('Pending'),
                    ]),
                SelectFilter::make('gender')
                    ->label(__('Gender'))
                    ->options([
                        'male' => __('Male'),
                        'female' => __('Female'),
                        'non_binary' => __('Non-binary'),
                        'other' => __('Other'),
                    ]),
                SelectFilter::make('languages')
                    ->label(__('Languages'))
                    ->relationship('languages', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Provider $record): bool => $record->status === Provider::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (Provider $record): void {
                        app(ProviderProfileService::class)->approve($record);
                        Notification::make()->title(__('Application approved'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Provider $record): bool => $record->status === Provider::STATUS_PENDING)
                    ->schema([
                        Textarea::make('reason')->label(__('Reason for rejection'))->required(),
                    ])
                    ->action(function (array $data, Provider $record): void {
                        app(ProviderProfileService::class)->reject($record, $data['reason']);
                        Notification::make()->title(__('Application rejected'))->danger()->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_name');
    }
}
