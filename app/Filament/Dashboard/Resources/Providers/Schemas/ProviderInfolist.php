<?php

namespace App\Filament\Dashboard\Resources\Providers\Schemas;

use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identity'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')
                            ->label(__('Name'))
                            ->state(fn (Provider $record): string => trim("{$record->first_name} {$record->last_name}")),
                        TextEntry::make('business_name')
                            ->label(__('Business Name'))
                            ->placeholder('—'),
                        TextEntry::make('email')
                            ->label(__('Email'))
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label(__('Phone'))
                            ->placeholder('—'),
                        TextEntry::make('phone_alt')
                            ->label(__('Alternate Phone'))
                            ->placeholder('—'),
                        ImageEntry::make('photo')
                            ->label(__('Profile Photo'))
                            ->disk('public')
                            ->visibility('public')
                            ->circular()
                            ->placeholder('—'),
                    ]),

                Section::make(__('Address'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')
                            ->label(__('Street Address'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('city')->label(__('City'))->placeholder('—'),
                        TextEntry::make('state')->label(__('State'))->placeholder('—'),
                        TextEntry::make('zip')->label(__('ZIP'))->placeholder('—'),
                        TextEntry::make('latitude')->label(__('Latitude'))->placeholder('—'),
                        TextEntry::make('longitude')->label(__('Longitude'))->placeholder('—'),
                    ]),

                // Two-up rows: Classification | Matching, then Status | Payroll. Notes
                // stays full-width below (its intended row-mate, Credentials, is a
                // relation manager that renders on its own layer beneath the infolist).
                Grid::make(2)
                    ->schema([
                        Section::make(__('Classification'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('discipline.name')->label(TenantConfig::entityLabel('discipline', __('Discipline')))->placeholder('—'),
                                TextEntry::make('tier.name')->label(__('Tier'))->badge()->placeholder('—'),
                                TextEntry::make('office.name')->label(__('Office'))->placeholder('—'),
                                TextEntry::make('gender')->label(__('Gender'))->placeholder('—'),
                                TextEntry::make('specialties.name')
                                    ->label(__('Specialties'))
                                    ->badge()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                IconEntry::make('is_contractor')
                                    ->label(__('Is Contractor'))
                                    ->boolean(),
                            ]),

                        Section::make(__('Matching'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('radius_preferred_miles')
                                    ->label(__('Preferred Radius'))
                                    ->suffix(__(' mi')),
                                TextEntry::make('radius_max_miles')
                                    ->label(__('Maximum Radius'))
                                    ->suffix(__(' mi')),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make(__('Status'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('status')
                                    ->label(__('Status'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'pending' => 'warning',
                                        'inactive' => 'danger',
                                        default => 'gray',
                                    }),
                                IconEntry::make('is_active')
                                    ->label(__('Active'))
                                    ->boolean(),
                                TextEntry::make('deactivated_at')
                                    ->label(__('Deactivated At'))
                                    ->dateTime(config('app.datetime_format'))
                                    ->placeholder('—'),
                                TextEntry::make('deactivation_reason')
                                    ->label(__('Deactivation Reason'))
                                    ->placeholder('—'),
                            ]),

                        Section::make(__('Payroll'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('payroll_id')->label(__('Payroll ID'))->placeholder('—'),
                                TextEntry::make('tax_id')->label(__('Tax ID'))->placeholder('—'),
                            ]),
                    ]),

                Section::make(__('Calendar Feed'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('calendar_feed_url')
                            ->label(__('Feed URL'))
                            ->state(fn (Provider $record): ?string => $record->calendarFeedUrl())
                            ->placeholder(__('Not generated — the provider creates this from their profile.'))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('calendar_token_generated_at')
                            ->label(__('Generated'))
                            ->dateTime(config('app.datetime_format'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('Notes'))
                    ->schema([
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
