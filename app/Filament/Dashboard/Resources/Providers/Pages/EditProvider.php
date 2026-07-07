<?php

namespace App\Filament\Dashboard\Resources\Providers\Pages;

use App\Filament\Dashboard\Resources\Providers\Concerns\PersistsOtherSpecialtyNote;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Specialty;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    use PersistsOtherSpecialtyNote;

    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $otherId = Specialty::otherId(Filament::getTenant()?->id);

        if ($otherId !== null) {
            $data['specialty_other_note'] = $this->record->specialties()
                ->where('sp_specialties.id', $otherId)
                ->first()?->pivot?->notes;
        }

        return $data;
    }

    /**
     * Defense in depth for the field scoping: the privileged fields are already hidden from
     * sp_staff in the form, but strip them here too so a forged request can never write them.
     * Absent keys leave the stored values untouched on an edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! SpRoleAccess::isHrOrAdmin()) {
            unset(
                $data['tier_id'],
                $data['payroll_id'],
                $data['tax_id'],
                $data['status'],
                $data['is_active'],
                $data['deactivated_at'],
                $data['deactivation_reason'],
            );
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistOtherSpecialtyNote();
        // Runs after the disciplines relationship has synced: flag the primary pivot row
        // and point the legacy discipline_id column at it.
        $this->record->assignPrimaryDiscipline();
    }
}
