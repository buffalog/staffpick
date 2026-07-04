<?php

namespace App\Filament\Dashboard\Resources\Providers\Pages;

use App\Filament\Dashboard\Resources\Providers\Concerns\PersistsOtherSpecialtyNote;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProvider extends CreateRecord
{
    use PersistsOtherSpecialtyNote;

    protected static string $resource = ProviderResource::class;

    protected function afterCreate(): void
    {
        $this->persistOtherSpecialtyNote();
        // Runs after the disciplines relationship has synced: flag the primary pivot row
        // and point the legacy discipline_id column at it.
        $this->record->assignPrimaryDiscipline();
    }
}
