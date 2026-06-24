<?php

namespace App\Filament\Dashboard\Resources\ProviderApplications;

use App\Filament\Dashboard\Resources\ProviderApplications\Pages\ListProviderApplications;
use App\Filament\Dashboard\Resources\ProviderApplications\Pages\ViewProviderApplication;
use App\Filament\Dashboard\Resources\ProviderApplications\Schemas\ProviderApplicationInfolist;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\ProviderApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderApplicationResource extends Resource
{
    protected static ?string $model = ProviderApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'email';

    public static function infolist(Schema $schema): Schema
    {
        return ProviderApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Name'))
                    ->state(fn (ProviderApplication $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')->label(__('Email'))->searchable(),
                TextColumn::make('discipline')->label(__('Discipline'))->placeholder('—'),
                TextColumn::make('city')->label(__('City'))->placeholder('—'),
                TextColumn::make('submitted_at')->label(__('Submitted At'))->dateTime(config('app.datetime_format'))->placeholder('—')->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        ProviderApplication::STATUS_SUBMITTED => 'warning',
                        ProviderApplication::STATUS_APPROVED => 'success',
                        ProviderApplication::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ProviderApplication::STATUS_SUBMITTED => __('Submitted'),
                        ProviderApplication::STATUS_APPROVED => __('Approved'),
                        ProviderApplication::STATUS_REJECTED => __('Rejected'),
                    ])
                    ->default(ProviderApplication::STATUS_SUBMITTED),
            ])
            ->recordUrl(fn (ProviderApplication $record): string => ViewProviderApplication::getUrl(['record' => $record]))
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        // Drafts never reach staff — only submitted/approved/rejected applications.
        return parent::getEloquentQuery()->where('status', '!=', ProviderApplication::STATUS_DRAFT);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderApplications::route('/'),
            'view' => ViewProviderApplication::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Our Providers');
    }

    public static function getNavigationLabel(): string
    {
        return __('Applications');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', ProviderApplication::STATUS_SUBMITTED)
            ->count();

        return $count > 0 ? (string) $count : null;
    }
}
