<?php

namespace App\Filament\Dashboard\Resources\Providers\Schemas;

use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Colored band header (name + discipline chips + tier) — matches the card
                // grid exactly, reusing the chip partials.
                View::make('staffpick.providers.partials.provider-view-header')
                    ->columnSpanFull(),

                // Merged Identity/Address/Status/Payroll card, sharing the header's accent
                // border. Latitude/longitude are deliberately not shown anywhere here.
                View::make('staffpick.providers.partials.provider-merged-card')
                    ->columnSpanFull(),

                Section::make(__('Classification'))
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('disciplines.name')
                            ->label(TenantConfig::entityLabel('discipline', __('Discipline')))
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('tier.name')->label(__('Tier'))->badge()->placeholder('—'),
                        TextEntry::make('gender')->label(__('Gender'))->placeholder('—'),
                        TextEntry::make('office.name')->label(__('Office'))->placeholder('—'),
                        TextEntry::make('specialties.name')
                            ->label(__('Specialties'))
                            ->badge()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        IconEntry::make('is_contractor')->label(__('Is Contractor'))->boolean(),
                    ]),

                Section::make(__('Matching'))
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('radius_preferred_miles')->label(__('Preferred Radius'))->suffix(__(' mi')),
                        TextEntry::make('radius_max_miles')->label(__('Maximum Radius'))->suffix(__(' mi')),
                    ]),

                Section::make(__('Calendar Feed'))
                    ->collapsible()
                    ->collapsed()
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
                    ->collapsible()
                    ->collapsed()
                    // Dot whenever there's any notes content — surfaces the 13 tier-unconfirmed
                    // providers (and any other note) without forcing the section open.
                    ->afterHeader(fn (Provider $record): ?HtmlString => filled($record->notes)
                        ? new HtmlString('<span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500" title="'.__('Has notes').'"></span>')
                        : null)
                    ->schema([
                        TextEntry::make('notes')
                            ->hiddenLabel()
                            ->placeholder(__('No notes.'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
