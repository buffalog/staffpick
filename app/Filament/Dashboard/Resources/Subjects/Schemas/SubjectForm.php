<?php

namespace App\Filament\Dashboard\Resources\Subjects\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectForm
{
    /**
     * @return array<string, string>
     */
    private static function genderOptions(): array
    {
        return [
            'male' => __('Male'),
            'female' => __('Female'),
            'non_binary' => __('Non-binary'),
            'other' => __('Other'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /**
     * The full subject field set, reused by the standalone Subjects resource and by
     * the create/edit-subject modals on the intake form (so a case can capture the
     * complete patient record — insurance included — in one flow).
     *
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('Identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')
                        ->label(__('First Name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->label(__('Last Name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->tel()
                        ->maxLength(30),
                    TextInput::make('phone_alt')
                        ->label(__('Alternate Phone'))
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make(__('Emergency Contact'))
                ->columns(3)
                ->schema([
                    TextInput::make('alt_contact_name')
                        ->label(__('Contact Name'))
                        ->maxLength(255),
                    TextInput::make('alt_contact_phone')
                        ->label(__('Contact Phone'))
                        ->tel()
                        ->maxLength(30),
                    TextInput::make('alt_contact_relationship')
                        ->label(__('Relationship'))
                        ->maxLength(255),
                ]),

            Section::make(__('Address'))
                ->columns(2)
                ->schema([
                    TextInput::make('address')
                        ->label(__('Street Address'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('address_2')
                        ->label(__('Address Line 2'))
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
                    TextInput::make('latitude')
                        ->label(__('Latitude'))
                        ->numeric()
                        ->step('0.0000001')
                        ->minValue(-90)
                        ->maxValue(90),
                    TextInput::make('longitude')
                        ->label(__('Longitude'))
                        ->numeric()
                        ->step('0.0000001')
                        ->minValue(-180)
                        ->maxValue(180),
                ]),

            Section::make(__('Demographics'))
                ->columns(3)
                ->schema([
                    DatePicker::make('date_of_birth')
                        ->label(__('Date of Birth'))
                        ->maxDate(now()),
                    Select::make('gender')
                        ->label(__('Gender'))
                        ->options(self::genderOptions()),
                    TextInput::make('preferred_language')
                        ->label(__('Preferred Language'))
                        ->maxLength(255),
                ]),

            Section::make(__('Medical'))
                ->columns(2)
                ->schema([
                    Textarea::make('diagnosis')
                        ->label(__('Diagnosis'))
                        ->rows(2)
                        ->columnSpanFull(),
                    TextInput::make('pcp_name')
                        ->label(__('Primary Care Provider'))
                        ->maxLength(255),
                    TextInput::make('pcp_phone')
                        ->label(__('PCP Phone'))
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make(__('Insurance'))
                ->columns(3)
                ->schema([
                    Select::make('insurance_type_id')
                        ->label(__('Insurance Type'))
                        ->relationship('insuranceType', 'name')
                        ->searchable()
                        ->preload(),
                    TextInput::make('insurance_id')
                        ->label(__('Insurance ID'))
                        ->maxLength(255),
                    TextInput::make('insurance_group')
                        ->label(__('Insurance Group'))
                        ->maxLength(255),
                ]),

            Section::make(__('Matching Preferences'))
                ->columns(2)
                ->schema([
                    Select::make('provider_gender_preference')
                        ->label(__('Preferred Provider Gender'))
                        ->options(self::genderOptions()),
                    TextInput::make('language_preference')
                        ->label(__('Language Preference'))
                        ->maxLength(255),
                ]),

            Section::make(__('Status'))
                ->schema([
                    Toggle::make('is_active')
                        ->label(__('Active'))
                        ->default(true),
                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }
}
