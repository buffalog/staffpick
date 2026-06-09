<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Pages;

use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReferralSource extends ViewRecord
{
    protected static string $resource = ReferralSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
