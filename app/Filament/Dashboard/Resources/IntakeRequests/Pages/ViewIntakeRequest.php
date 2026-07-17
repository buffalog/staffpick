<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Concerns\LogsRecordView;
use App\Filament\Dashboard\Resources\IntakeRequests\Actions\DispatchOffersAction;
use App\Filament\Dashboard\Resources\IntakeRequests\Actions\FindMatchesAction;
use App\Filament\Dashboard\Resources\IntakeRequests\Actions\RetriggerMatchingAction;
use App\Filament\Dashboard\Resources\IntakeRequests\Concerns\AssignsMatchedProviders;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIntakeRequest extends ViewRecord
{
    use AssignsMatchedProviders;
    use LogsRecordView;

    protected static string $resource = IntakeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FindMatchesAction::make(),
            DispatchOffersAction::make(),
            RetriggerMatchingAction::make(),
            EditAction::make(),
        ];
    }
}
