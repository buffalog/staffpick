<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\Concerns\AssignsMatchedProviders;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntakeRequests extends ListRecords
{
    use AssignsMatchedProviders;

    protected static string $resource = IntakeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
