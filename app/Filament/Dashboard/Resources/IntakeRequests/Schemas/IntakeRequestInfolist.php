<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Schemas;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\IntakeRequest;
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
                        TextEntry::make('discipline.name')->label(__('Discipline'))->placeholder('—'),
                        TextEntry::make('office.name')->label(__('Office'))->placeholder('—'),
                        TextEntry::make('assigner.name')->label(__('Assigner'))->placeholder('—'),
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
