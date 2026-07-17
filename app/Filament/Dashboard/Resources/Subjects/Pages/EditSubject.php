<?php

namespace App\Filament\Dashboard\Resources\Subjects\Pages;

use App\Filament\Concerns\LogsRecordView;
use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSubject extends EditRecord
{
    use LogsRecordView;

    protected static string $resource = SubjectResource::class;

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
