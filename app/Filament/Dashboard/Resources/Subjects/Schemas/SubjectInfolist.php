<?php

namespace App\Filament\Dashboard\Resources\Subjects\Schemas;

use App\Models\StaffPick\Subject;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectInfolist
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
                            ->state(fn (Subject $record): string => trim("{$record->first_name} {$record->last_name}")),
                        IconEntry::make('is_active')->label(__('Active'))->boolean(),
                        TextEntry::make('phone')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('phone_alt')->label(__('Alternate Phone'))->placeholder('—'),
                    ]),

                Section::make(__('Emergency Contact'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('alt_contact_name')->label(__('Contact Name'))->placeholder('—'),
                        TextEntry::make('alt_contact_phone')->label(__('Contact Phone'))->placeholder('—'),
                        TextEntry::make('alt_contact_relationship')->label(__('Relationship'))->placeholder('—'),
                    ]),

                Section::make(__('Address'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')->label(__('Street Address'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('address_2')->label(__('Address Line 2'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('city')->label(__('City'))->placeholder('—'),
                        TextEntry::make('state')->label(__('State'))->placeholder('—'),
                        TextEntry::make('zip')->label(__('ZIP'))->placeholder('—'),
                    ]),

                Section::make(__('Demographics'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('date_of_birth')->label(__('Date of Birth'))->date()->placeholder('—'),
                        TextEntry::make('gender')->label(__('Gender'))->placeholder('—'),
                        TextEntry::make('preferred_language')->label(__('Preferred Language'))->placeholder('—'),
                    ]),

                Section::make(__('Medical'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('diagnosis')->label(__('Diagnosis'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('pcp_name')->label(__('Primary Care Provider'))->placeholder('—'),
                        TextEntry::make('pcp_phone')->label(__('PCP Phone'))->placeholder('—'),
                    ]),

                Section::make(__('Insurance'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('insuranceType.name')->label(__('Insurance Type'))->placeholder('—'),
                        TextEntry::make('insurance_id')->label(__('Insurance ID'))->placeholder('—'),
                        TextEntry::make('insurance_group')->label(__('Insurance Group'))->placeholder('—'),
                    ]),

                Section::make(__('Matching Preferences'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('provider_gender_preference')->label(__('Preferred Provider Gender'))->placeholder('—'),
                        TextEntry::make('language_preference')->label(__('Language Preference'))->placeholder('—'),
                    ]),

                Section::make(__('Notes'))
                    ->schema([
                        TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
