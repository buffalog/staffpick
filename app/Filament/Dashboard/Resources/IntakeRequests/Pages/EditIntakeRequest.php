<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIntakeRequest extends EditRecord
{
    protected static string $resource = IntakeRequestResource::class;

    /**
     * Render the form without its <form> wrapper so Save can live in the header.
     * Header actions render outside the form element, so a submit-type button there
     * wouldn't fire; without the wrapper, getSaveFormAction() saves via a Livewire
     * ->action('save') call instead, which works from anywhere on the page.
     */
    public function hasFormWrapper(): bool
    {
        return false;
    }

    /**
     * Save changes / Cancel are relocated to the header actions, so the bottom
     * form-action bar is intentionally empty.
     *
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Save Changes (success) -> Cancel (gray) -> Delete, per the requested order.
            $this->getSaveFormAction()->color('success'),
            $this->getCancelFormAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ViewAction::make(),
        ];
    }
}
