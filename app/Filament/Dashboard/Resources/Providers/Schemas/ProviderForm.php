<?php

namespace App\Filament\Dashboard\Resources\Providers\Schemas;

use App\Constants\UsStates;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Specialty;
use App\Models\StaffPick\TenantConfig;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                        TextInput::make('business_name')
                            ->label(__('Business Name'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
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
                        Select::make('state')
                            ->label(__('State'))
                            ->options(UsStates::options())
                            ->searchable(),
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

                Section::make(__('Classification'))
                    ->columns(2)
                    ->schema([
                        Select::make('disciplines')
                            ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                            ->relationship('disciplines', 'name')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText(__('A provider may hold more than one; the first is treated as primary.'))
                            // Specialties are scoped to the chosen disciplines; clear them
                            // (and any write-in) on change so nothing stale persists.
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('specialties', []);
                                $set('specialty_other_note', null);
                            }),
                        Select::make('tier_id')
                            ->label(__('Tier'))
                            ->relationship('tier', 'name')
                            ->searchable()
                            ->preload()
                            // Privileged: sp_staff may not set tier.
                            ->visible(fn (): bool => SpRoleAccess::isHrOrAdmin()),
                        Select::make('office_id')
                            ->label(__('Office'))
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('gender')
                            ->label(__('Gender'))
                            ->options([
                                'male' => __('Male'),
                                'female' => __('Female'),
                                'non_binary' => __('Non-binary'),
                                'other' => __('Other'),
                            ]),
                        Select::make('specialties')
                            ->label(__('Specialties'))
                            ->relationship(
                                'specialties',
                                'name',
                                fn (Builder $query, Get $get): Builder => filled($get('disciplines'))
                                    ? $query->whereHas('disciplines', fn (Builder $disciplineQuery) => $disciplineQuery->whereIn('sp_disciplines.id', (array) $get('disciplines')))
                                    : $query->whereRaw('1 = 0'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('disciplines')))
                            ->helperText(__('Select a discipline first to see its specialties.'))
                            ->columnSpanFull(),
                        // Persisted to the sp_provider_specialties pivot in the Create/Edit
                        // page hooks (it isn't a Provider column), so it's not dehydrated here.
                        TextInput::make('specialty_other_note')
                            ->label(__('Other specialty — please specify'))
                            ->maxLength(255)
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => self::isOtherSpecialtySelected($get))
                            ->columnSpanFull(),
                        Select::make('languages')
                            ->label(__('Languages Spoken'))
                            ->relationship('languages', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Toggle::make('is_contractor')
                            ->label(__('Is Contractor'))
                            ->helperText(__('Whether this provider is a 1099 contractor rather than an employee.'))
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Matching'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('radius_preferred_miles')
                            ->label(__('Preferred Radius (miles)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(15),
                        TextInput::make('radius_max_miles')
                            ->label(__('Maximum Radius (miles)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(25),
                        Toggle::make('can_adjust_own_service_zones')
                            ->label(__('Can Adjust Own Service Zones'))
                            ->helperText(__('Whether this provider may adjust their own service zones.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Status'))
                    ->columns(2)
                    // Privileged: active/deactivated status is hr/admin/super-admin only.
                    ->visible(fn (): bool => SpRoleAccess::isHrOrAdmin())
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'active' => __('Active'),
                                'inactive' => __('Inactive'),
                                'pending' => __('Pending'),
                            ])
                            ->default('active')
                            ->required()
                            ->live(),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                        DateTimePicker::make('deactivated_at')
                            ->label(__('Deactivated At'))
                            ->visible(fn (Get $get): bool => $get('status') !== 'active'),
                        TextInput::make('deactivation_reason')
                            ->label(__('Deactivation Reason'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('status') !== 'active'),
                    ]),

                Section::make(__('Payroll'))
                    ->columns(2)
                    // Privileged: payroll id + tax id are hr/admin/super-admin only.
                    ->visible(fn (): bool => SpRoleAccess::isHrOrAdmin())
                    ->schema([
                        TextInput::make('payroll_id')
                            ->label(__('Payroll ID'))
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->label(__('Tax ID'))
                            ->maxLength(255),
                    ]),

                Section::make(__('Notes'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Whether the tenant's "Other (write in)" specialty is selected, revealing the
     * free-text write-in field.
     */
    private static function isOtherSpecialtySelected(Get $get): bool
    {
        $otherId = Specialty::otherId(Filament::getTenant()?->id);

        return $otherId !== null && in_array($otherId, array_map('intval', (array) $get('specialties')), true);
    }
}
