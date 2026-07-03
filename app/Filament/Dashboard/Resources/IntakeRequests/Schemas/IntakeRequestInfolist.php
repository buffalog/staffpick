<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Schemas;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\TenantConfig;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class IntakeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Row 1: Case (left) | Assignment + Notes stacked (right).
                Grid::make(2)
                    ->schema([
                        Section::make(__('Case'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('reference_number')->label(__('Reference Number'))->placeholder('—'),
                                TextEntry::make('status')
                                    ->label(__('Status'))
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => IntakeRequestResource::statusOptions()[$state] ?? str($state)->headline())
                                    ->color(fn (string $state): string => IntakeRequestResource::statusColor($state)),
                                TextEntry::make('subject_name')
                                    ->label(__('Case'))
                                    ->state(fn (IntakeRequest $record): ?string => $record->subject
                                        ? trim("{$record->subject->first_name} {$record->subject->last_name}")
                                        : null)
                                    ->placeholder('—')
                                    // One-click case → patient: jump to the full subject record
                                    // to review or complete contacts, insurance, diagnosis, etc.
                                    ->url(fn (IntakeRequest $record): ?string => $record->subject_id
                                        ? SubjectResource::getUrl('edit', ['record' => $record->subject_id])
                                        : null)
                                    ->color('primary')
                                    ->icon(Heroicon::OutlinedUser),
                                TextEntry::make('referralSource.name')->label(__('Referral Source'))->placeholder('—'),
                                TextEntry::make('referring_clinician_name')->label(__('Referring clinician'))->placeholder('—'),
                                TextEntry::make('referring_clinician_phone')->label(__('Referring clinician phone'))->placeholder('—'),
                                TextEntry::make('discipline.name')->label(TenantConfig::entityLabel('discipline', __('Discipline')))->placeholder('—'),
                                TextEntry::make('office.name')->label(__('Office'))->placeholder('—'),
                                TextEntry::make('assigner.name')->label(__('Assigner'))->placeholder('—'),
                            ]),

                        // Right column: Assignment (if any) with Notes directly beneath it.
                        Grid::make(1)
                            ->schema([
                                Section::make(__('Assignment'))
                                    ->columns(2)
                                    ->visible(fn (IntakeRequest $record): bool => $record->currentAssignment !== null)
                                    ->schema([
                                        TextEntry::make('assignment_provider')
                                            ->label(__('Provider'))
                                            ->state(fn (IntakeRequest $record): ?string => $record->currentAssignment?->provider
                                                ? trim("{$record->currentAssignment->provider->first_name} {$record->currentAssignment->provider->last_name}")
                                                : null)
                                            ->placeholder('—'),
                                        TextEntry::make('currentAssignment.status')
                                            ->label(__('Assignment Status'))
                                            ->badge()
                                            ->formatStateUsing(fn (?string $state): ?string => filled($state) ? str($state)->headline() : null)
                                            ->placeholder('—'),
                                        TextEntry::make('currentAssignment.assigned_at')
                                            ->label(__('Assigned At'))
                                            ->date('M j, Y')
                                            ->placeholder('—'),
                                    ]),

                                Section::make(__('Notes'))
                                    ->schema([
                                        TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                                    ]),
                            ]),
                    ]),

                // Row 2: Service (left) | Matching & Flags (right).
                Grid::make(2)
                    ->schema([
                        Section::make(__('Service'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('authorization_number')->label(__('Authorization Number'))->placeholder('—'),
                                TextEntry::make('frequency')->label(__('Frequency'))->placeholder('—'),
                                TextEntry::make('start_date')->label(__('Start of Care date'))->date()->placeholder('—'),
                                TextEntry::make('end_date')->label(__('End Date'))->date()->placeholder('—'),
                                TextEntry::make('visits_authorized')->label(__('Visits Authorized'))->placeholder('—'),
                                TextEntry::make('visits_completed')->label(__('Visits Completed')),
                                TextEntry::make('visit_type')->label(__('Visit Type'))->placeholder('—'),
                            ]),

                        Section::make(__('Matching & Flags'))
                            ->columns(2)
                            ->schema([
                                // Constraints captured at intake that directly drive matching —
                                // shown so schedulers can see what's active before Find Matches.
                                TextEntry::make('subject.provider_gender_preference')
                                    ->label(__('Gender Preference'))
                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? str($state)->title() : null)
                                    ->placeholder(__('No preference')),
                                TextEntry::make('subject.language_preference')
                                    ->label(__('Language Preference'))
                                    ->placeholder(__('No preference')),
                                TextEntry::make('requested_provider')
                                    ->label(__('Requested Provider'))
                                    ->state(fn (IntakeRequest $record): ?string => $record->requestedProvider
                                        ? trim("{$record->requestedProvider->first_name} {$record->requestedProvider->last_name}")
                                        : null)
                                    ->placeholder(__('None')),
                                TextEntry::make('specialties.name')
                                    ->label(__('Requested Specialties'))
                                    ->badge()
                                    ->placeholder(__('None'))
                                    ->columnSpanFull(),
                                TextEntry::make('radius_miles')->label(__('Radius'))->suffix(__(' mi'))->placeholder('—'),
                                IconEntry::make('is_partial_staffing')->label(__('Partial staffing'))->boolean(),
                                TextEntry::make('assistant_clinician_name')
                                    ->label(__('Assistant clinician (in-house)'))
                                    ->placeholder('—')
                                    ->visible(fn (IntakeRequest $record): bool => (bool) $record->is_partial_staffing),
                                TextEntry::make('lead_clinician_name')
                                    ->label(__('Lead clinician'))
                                    ->state(fn (IntakeRequest $record): ?string => $record->leadClinician
                                        ? trim("{$record->leadClinician->first_name} {$record->leadClinician->last_name}")
                                        : null)
                                    ->placeholder('—'),
                                IconEntry::make('manual_assignment')->label(__('Manual Assignment'))->boolean(),
                                IconEntry::make('needs_emr_transition')->label(__('Needs EMR Transition'))->boolean(),
                                IconEntry::make('paperwork_complete')->label(__('Paperwork Complete'))->boolean(),
                            ]),
                    ]),

                // Row 3: External References (full width — its former row-mate, Notes,
                // now lives under Assignment).
                Section::make(__('External References'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('emr_id')->label(__('EMR ID'))->placeholder('—'),
                        TextEntry::make('slack_channel_id')->label(__('Slack Channel ID'))->placeholder('—'),
                    ]),
            ]);
    }
}
