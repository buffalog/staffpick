<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferralSourceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Details'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('Name')),
                        TextEntry::make('contact_name')->label(__('Contact Name'))->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'delinquent' => 'danger',
                                'inactive' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('group.name')->label(__('Group'))->placeholder('—'),
                        TextEntry::make('billing_terms_days')->label(__('Billing Terms'))->suffix(__(' days')),
                        TextEntry::make('portal_username')->label(__('Portal Username'))->placeholder('—'),
                    ]),

                Section::make(__('Contact'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('fax')->label(__('Fax'))->placeholder('—'),
                        TextEntry::make('email')->label(__('Email'))->placeholder('—')->copyable(),
                    ]),

                Section::make(__('Address'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')->label(__('Street Address'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('city')->label(__('City'))->placeholder('—'),
                        TextEntry::make('state')->label(__('State'))->placeholder('—'),
                        TextEntry::make('zip')->label(__('ZIP'))->placeholder('—'),
                    ]),
            ]);
    }
}
