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
     * Render the form without its <form> wrapper so Create can live in the header.
     * Header actions render outside the form element, so a submit-type button there
     * wouldn't fire; without the wrapper, getCreateFormAction() creates via a Livewire
     * ->action('create') call instead, which works from anywhere on the page.
     * Mirrors EditIntakeRequest.
     */
    public function hasFormWrapper(): bool
    {
        return false;
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
     * Create / Create another / Cancel are relocated to the header actions, so the bottom
     * form-action bar is intentionally empty.
     *
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
