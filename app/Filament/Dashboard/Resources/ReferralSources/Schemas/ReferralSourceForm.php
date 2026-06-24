<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferralSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Details'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('contact_name')
                            ->label(__('Contact Name'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'active' => __('Active'),
                                'pending' => __('Pending'),
                                'inactive' => __('Inactive'),
                                'delinquent' => __('Delinquent'),
                            ])
                            ->default('active')
                            ->required(),
                        Select::make('group_id')
                            ->label(__('Group'))
                            ->relationship('group', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->default(true),
                            ]),
                        TextInput::make('billing_terms_days')
                            ->label(__('Billing Terms (days)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(14),
                        TextInput::make('portal_username')
                            ->label(__('Portal Username'))
                            ->maxLength(255),
                    ]),

                Section::make(__('Contact'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('fax')
                            ->label(__('Fax'))
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Address'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('address')
                            ->label(__('Street Address'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label(__('City'))
                            ->maxLength(255),
                        TextInput::make('state')
                            ->label(__('State'))
                            ->maxLength(10),
                        TextInput::make('zip')
                            ->label(__('ZIP'))
                            ->maxLength(20),
                    ]),
            ]);
    }
}
