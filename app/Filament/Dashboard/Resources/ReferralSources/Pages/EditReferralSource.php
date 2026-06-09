<?php

namespace App\Filament\Dashboard\Resources\ReferralSources\Pages;

use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReferralSource extends EditRecord
{
    protected static string $resource = ReferralSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
