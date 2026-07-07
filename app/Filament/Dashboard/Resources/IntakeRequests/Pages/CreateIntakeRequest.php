<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\IntakeRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateIntakeRequest extends CreateRecord
{
    protected static string $resource = IntakeRequestResource::class;

    /**
     * The form defaults a new case's status to 'draft' so the create page never implies a
     * live case before anything is saved. A saved case starts life as 'unmatched' — flip it
     * here, but only when the user left the placeholder in place (an explicit status stands).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? null) === IntakeRequest::STATUS_DRAFT) {
            $data['status'] = IntakeRequest::STATUS_UNMATCHED;
        }

        return $data;
    }

    /**
     * Present the create/cancel actions at the top of the page instead of the footer.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCreateAnotherFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
