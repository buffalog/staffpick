<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class IntakeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Hero merged card: colored discipline band (patient name + discipline chip)
                // as the header, with Ref / status pill / referral + discipline details below.
                // Matches the provider View page's merged-card layout.
                View::make('staffpick.intake-requests.partials.intake-request-merged-card')
                    ->columnSpanFull(),

                // Assignment / Service / Matching & Flags / Notes as flat bordered accordions
                // (the shared <x-sp-accordion> pattern), not Filament's boxed Sections.
                View::make('staffpick.intake-requests.partials.intake-request-accordions')
                    ->columnSpanFull(),
            ]);
    }
}
