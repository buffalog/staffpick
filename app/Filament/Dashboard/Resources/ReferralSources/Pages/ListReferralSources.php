<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Pages;

use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferralSources extends ListRecords
{
    protected static string $resource = ReferralSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
