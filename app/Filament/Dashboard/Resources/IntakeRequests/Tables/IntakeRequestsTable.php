<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Tables;

use App\Filament\Dashboard\Resources\IntakeRequests\Actions\DispatchOffersAction;
use App\Filament\Dashboard\Resources\IntakeRequests\Actions\FindMatchesAction;
use App\Filament\Dashboard\Resources\IntakeRequests\Actions\RetriggerMatchingAction;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\TenantConfig;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class IntakeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label(__('Reference'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject.last_name')
                    ->label(__('Subject'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('referralSource.name')
                    ->label(__('Referral Source'))
                    ->toggleable(),
                TextColumn::make('discipline.name')
                    ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                    ->toggleable(),
                TextColumn::make('subject.provider_gender_preference')
                    ->label(__('Gender Pref.'))
                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? str($state)->title() : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subject.language_preference')
                    ->label(__('Language Pref.'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => IntakeRequestResource::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => IntakeRequestResource::statusColor($state))
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('Start Date'))
                    ->date(config('app.date_format'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('assigner.name')
                    ->label(__('Assigner'))
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(IntakeRequestResource::statusOptions()),
                SelectFilter::make('discipline')
                    ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                    ->relationship('discipline', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('referralSource')
                    ->label(__('Referral Source'))
                    ->relationship('referralSource', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                FindMatchesAction::make(),
                DispatchOffersAction::make(),
                RetriggerMatchingAction::make(),
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
            ->defaultSort('created_at', 'desc');
    }
}
