<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\Actions\FindMatchesAction;
use App\Filament\Dashboard\Resources\IntakeRequests\Concerns\AssignsMatchedProviders;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIntakeRequest extends ViewRecord
{
    use AssignsMatchedProviders;

    protected static string $resource = IntakeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FindMatchesAction::make(),
            EditAction::make(),
        ];
    }
}
