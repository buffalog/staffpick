<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Schemas;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\TenantConfig;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class IntakeRequestForm
{
    /**
     * Roles that may be selected as a case's assigner. Provider-only users are excluded.
     *
     * @var array<int, string>
     */
    private const ASSIGNER_ROLES = [
        TenancyPermissionConstants::ROLE_SP_ADMIN,
        TenancyPermissionConstants::ROLE_SP_STAFF,
        TenancyPermissionConstants::ROLE_SP_HR,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Top card — always visible, no accordion. A required field must never hide
                // behind a collapsed section on a create form. Merges Case + Status.
                Section::make(__('Patient & status'))
                    ->columns(2)
                    ->schema([
                        Select::make('subject_id')
                            ->label(__('Patient'))
                            ->relationship('subject', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn (Subject $record): string => trim("{$record->first_name} {$record->last_name}"))
                            ->searchable(['first_name', 'last_name'])
                            ->preload()
                            ->required()
                            // Capture or complete the full patient record (contacts, insurance,
                            // etc.) without leaving the intake — the subject drives matching.
                            ->createOptionForm(SubjectForm::components())
                            ->editOptionForm(SubjectForm::components()),
                        TextInput::make('reference_number')
                            ->label(__('Reference Number'))
                            ->maxLength(255),
                        Select::make('referral_source_id')
                            ->label(__('Referral Source'))
                            ->relationship('referralSource', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('discipline_id')
                            ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                            ->helperText(__('Only clinicians with this discipline will be matched.'))
                            ->relationship('discipline', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('referring_clinician_name')
                            ->label(__('Referring clinician name'))
                            ->helperText(__('The RN, case manager, or physician sending the referral.'))
                            ->maxLength(255),
                        TextInput::make('referring_clinician_phone')
                            ->label(__('Referring clinician phone'))
                            ->maxLength(30),
                        Select::make('assigner_user_id')
                            ->label(__('Assigner'))
                            // Only staff can be assigners — scope to sp_admin/sp_staff/sp_hr in
                            // this tenant so provider-only users never appear as options.
                            ->relationship(
                                'assigner',
                                'name',
                                fn (Builder $query): Builder => SpRoleAccess::usersHoldingAnySpRole(
                                    $query,
                                    Filament::getTenant()?->getKey(),
                                    self::ASSIGNER_ROLES,
                                ),
                            )
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(IntakeRequestResource::statusOptions())
                            // Composing a NEW case shows 'draft' so the page never implies a
                            // live case before anything is saved; CreateIntakeRequest flips it
                            // to 'unmatched' at persistence. Existing records keep their status.
                            ->default(IntakeRequest::STATUS_DRAFT)
                            ->required()
                            ->live(),
                        Select::make('on_hold_reason_id')
                            ->label(__('On-Hold Reason'))
                            ->relationship('onHoldReason', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('status') === IntakeRequest::STATUS_ON_HOLD),
                        Select::make('cancellation_reason_id')
                            ->label(__('Cancellation Reason'))
                            ->relationship('cancellationReason', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('status') === IntakeRequest::STATUS_CANCELLED),
                        Textarea::make('status_notes')
                            ->label(__('Status Notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Service details'))
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('authorization_number')
                            ->label(__('Authorization Number'))
                            ->maxLength(255),
                        TextInput::make('frequency')
                            ->label(__('Frequency'))
                            ->placeholder('e.g. 2x/week')
                            ->maxLength(255),
                        DatePicker::make('evaluation_date')
                            ->label(__('Evaluation Date')),
                        DatePicker::make('start_date')
                            ->label(__('Start of Care date')),
                        DatePicker::make('end_date')
                            ->label(__('End Date')),
                        TextInput::make('visits_authorized')
                            ->label(__('Visits Authorized'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('visits_completed')
                            ->label(__('Visits Completed'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('visit_type')
                            ->label(__('Visit Type'))
                            ->maxLength(255),
                    ]),

                Section::make(__('Matching preferences'))
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('radius_miles')
                            ->label(__('Radius (miles)'))
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('manual_assignment')
                            ->label(__('Manual Assignment')),
                        Toggle::make('is_partial_staffing')
                            ->label(__('Partial staffing'))
                            ->helperText(__('An assistant clinician is already placed in-house; only a lead clinician needs matching.'))
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('assistant_clinician_name')
                            ->label(__('Assistant clinician (in-house)'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('is_partial_staffing')),
                        Select::make('lead_clinician_id')
                            ->label(__('Lead clinician'))
                            ->disabled()
                            ->options(fn (): array => Provider::query()
                                ->where('tenant_id', Filament::getTenant()?->getKey())
                                ->where('status', Provider::STATUS_ACTIVE)
                                ->where('is_active', true)
                                ->orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (Provider $provider): array => [
                                    $provider->id => trim("{$provider->first_name} {$provider->last_name}"),
                                ])
                                ->all())
                            ->searchable()
                            ->placeholder(__('Populated after matching')),
                    ]),

                Section::make(__('Notes'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Files'))
                    ->schema([
                        Repeater::make('files')
                            ->relationship()
                            ->defaultItems(0)
                            ->schema([
                                FileUpload::make('file_path')
                                    ->label(__('File'))
                                    ->storeFileNamesIn('file_name')
                                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                    ->maxSize(10240)
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('label')
                                    ->label(__('Label'))
                                    ->maxLength(255),
                                Select::make('visibility')
                                    ->label(__('Visibility'))
                                    ->options([
                                        'internal' => __('Internal'),
                                        'referral_source' => __('Referral Source'),
                                    ])
                                    ->default('internal'),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('Add file'))
                            ->columnSpanFull(),
                    ]),

                // Low-priority metadata — collapsed by default. Office lives here because
                // nothing in matching/dispatch reads it yet (no seeder/factory populates it).
                Section::make(__('Flags and external references'))
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('needs_emr_transition')
                            ->label(__('Needs EMR Transition')),
                        Toggle::make('paperwork_complete')
                            ->label(__('Paperwork Complete')),
                        TextInput::make('emr_id')
                            ->label(__('EMR ID'))
                            ->maxLength(255),
                        TextInput::make('slack_channel_id')
                            ->label(__('Slack Channel ID'))
                            ->maxLength(255),
                        Select::make('office_id')
                            ->label(__('Office'))
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
