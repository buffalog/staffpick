<?php

namespace App\Filament\Dashboard\Resources\Providers\Schemas;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Single unified contact card: the colored name band (name + discipline chips +
                // tier) is the card header, with Identity/Address/Status/Payroll below it.
                View::make('staffpick.providers.partials.provider-merged-card')
                    ->columnSpanFull(),

                // Classification / Matching / Calendar Feed / Notes as flat bordered accordions,
                // stacked single-column (not Filament's boxed Sections). Credentials is the
                // relation manager, which renders full-width below.
                View::make('staffpick.providers.partials.provider-accordions')
                    ->columnSpanFull(),
            ]);
    }
}
