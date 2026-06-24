<?php

namespace App\Filament\Dashboard\Resources\ProviderApplications\Schemas;

use App\Models\StaffPick\ProviderApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProviderApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make(__('Identity'))->columns(2)->schema([
                    TextEntry::make('full_name')->label(__('Name'))->state(fn (ProviderApplication $r): string => $r->fullName()),
                    TextEntry::make('email')->label(__('Email'))->copyable(),
                    TextEntry::make('phone')->label(__('Phone'))->placeholder('—'),
                    TextEntry::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (string $state): string => str($state)->headline()),
                ]),
                Section::make(__('Address'))->columns(2)->schema([
                    TextEntry::make('street_address')->label(__('Street'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('city')->label(__('City'))->placeholder('—'),
                    TextEntry::make('state')->label(__('State'))->placeholder('—'),
                    TextEntry::make('zip')->label(__('ZIP'))->placeholder('—'),
                    TextEntry::make('coords')->label(__('Coordinates'))
                        ->state(fn (ProviderApplication $r): string => filled($r->latitude) ? "{$r->latitude}, {$r->longitude}" : '—'),
                ]),
            ]),

            Grid::make(2)->schema([
                Section::make(__('Classification'))->columns(2)->schema([
                    TextEntry::make('discipline')->label(__('Discipline'))->placeholder('—'),
                    TextEntry::make('gender')->label(__('Gender'))->placeholder('—'),
                    TextEntry::make('is_contractor')->label(__('Contractor'))->state(fn (ProviderApplication $r): string => $r->is_contractor ? __('Yes') : __('No')),
                    TextEntry::make('preferred_radius')->label(__('Preferred radius'))->suffix(__(' mi'))->placeholder('—'),
                    TextEntry::make('maximum_radius')->label(__('Maximum radius'))->suffix(__(' mi'))->placeholder('—'),
                ]),
                Section::make(__('Specialties & Service Area'))->schema([
                    TextEntry::make('specialties')->label(__('Specialties'))
                        ->state(fn (ProviderApplication $r): string => empty($r->specialties) ? __('None') : count($r->specialties).' '.__('selected')),
                    TextEntry::make('service_zones')->label(__('Service zone'))
                        ->state(fn (ProviderApplication $r): string => empty($r->service_zones)
                            ? __('None drawn')
                            : count($r->service_zones).' '.__('polygon points')),
                ]),
            ]),

            Section::make(__('Credential uploads'))->schema([
                TextEntry::make('credential_uploads')->hiddenLabel()
                    ->state(fn (ProviderApplication $r): HtmlString => self::renderCredentialLinks($r)),
            ]),

            Section::make(__('Review'))
                ->visible(fn (ProviderApplication $r): bool => $r->status !== ProviderApplication::STATUS_SUBMITTED)
                ->schema([
                    TextEntry::make('rejection_reason')->label(__('Rejection reason'))->placeholder('—')->visible(fn (ProviderApplication $r): bool => $r->status === ProviderApplication::STATUS_REJECTED),
                    TextEntry::make('reviewed_at')->label(__('Reviewed at'))->dateTime(config('app.datetime_format'))->placeholder('—'),
                ]),
        ]);
    }

    private static function renderCredentialLinks(ProviderApplication $application): HtmlString
    {
        $uploads = $application->credential_uploads ?? [];

        if (empty($uploads)) {
            return new HtmlString('<span class="text-gray-400">'.__('No documents uploaded').'</span>');
        }

        $items = collect($uploads)->map(function (array $upload, int $index) use ($application): string {
            $label = e(($upload['document_type'] ?? __('Document')).' — '.($upload['original_name'] ?? basename($upload['path'] ?? '')));
            $url = route('staffpick.application.credential', ['application' => $application->id, 'index' => $index]);

            return "<li><a href=\"{$url}\" class=\"text-primary-600 hover:underline\" target=\"_blank\">{$label}</a></li>";
        })->implode('');

        return new HtmlString("<ul class=\"list-disc space-y-1 pl-5 text-sm\">{$items}</ul>");
    }
}
