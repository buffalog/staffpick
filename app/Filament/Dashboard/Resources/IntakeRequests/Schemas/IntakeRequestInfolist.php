<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Schemas;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\TenantConfig;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IntakeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Case'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference_number')->label(__('Reference Number'))->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (string $state): string => IntakeRequestResource::statusColor($state)),
                        TextEntry::make('subject_name')
                            ->label(__('Subject'))
                            ->state(fn (IntakeRequest $record): ?string => $record->subject
                                ? trim("{$record->subject->first_name} {$record->subject->last_name}")
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('referralSource.name')->label(__('Referral Source'))->placeholder('—'),
                        TextEntry::make('discipline.name')->label(TenantConfig::entityLabel('discipline', __('Discipline')))->placeholder('—'),
                        TextEntry::make('office.name')->label(__('Office'))->placeholder('—'),
                        TextEntry::make('assigner.name')->label(__('Assigner'))->placeholder('—'),
                    ]),

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
                        TextEntry::make('currentAssignment.status')->label(__('Assignment Status'))->badge()->placeholder('—'),
                        TextEntry::make('currentAssignment.assigned_at')->label(__('Assigned At'))->dateTime()->placeholder('—'),
                    ]),

                Section::make(__('Service'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('authorization_number')->label(__('Authorization Number'))->placeholder('—'),
                        TextEntry::make('frequency')->label(__('Frequency'))->placeholder('—'),
                        TextEntry::make('start_date')->label(__('Start Date'))->date()->placeholder('—'),
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
                        TextEntry::make('specialties.name')
                            ->label(__('Requested Specialties'))
                            ->badge()
                            ->placeholder(__('None'))
                            ->columnSpanFull(),
                        TextEntry::make('radius_miles')->label(__('Radius'))->suffix(__(' mi'))->placeholder('—'),
                        IconEntry::make('manual_assignment')->label(__('Manual Assignment'))->boolean(),
                        IconEntry::make('needs_emr_transition')->label(__('Needs EMR Transition'))->boolean(),
                        IconEntry::make('paperwork_complete')->label(__('Paperwork Complete'))->boolean(),
                    ]),

                Section::make(__('External References'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('emr_id')->label(__('EMR ID'))->placeholder('—'),
                        TextEntry::make('slack_channel_id')->label(__('Slack Channel ID'))->placeholder('—'),
                    ]),

                Section::make(__('Notes'))
                    ->schema([
                        TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
