<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntakeRequest extends CreateRecord
{
    protected static string $resource = IntakeRequestResource::class;
}
