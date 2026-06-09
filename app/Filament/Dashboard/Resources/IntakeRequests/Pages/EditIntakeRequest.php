<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIntakeRequest extends EditRecord
{
    protected static string $resource = IntakeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
