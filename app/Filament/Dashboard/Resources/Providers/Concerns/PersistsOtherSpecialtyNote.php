<?php

namespace App\Filament\Dashboard\Resources\Providers\Concerns;

use App\Models\StaffPick\Specialty;
use Filament\Facades\Filament;

/**
 * Persists the write-in detail for the "Other (write in)" specialty onto the
 * sp_provider_specialties pivot from the Create/Edit Provider pages. The field is
 * not a Provider column (it's `->dehydrated(false)`), so the relationship sync done
 * by Filament leaves the pivot note untouched — this fills it in afterwards.
 */
trait PersistsOtherSpecialtyNote
{
    protected function persistOtherSpecialtyNote(): void
    {
        $otherId = Specialty::otherId(Filament::getTenant()?->id);

        if ($otherId === null) {
            return;
        }

        if ($this->record->specialties()->where('sp_specialties.id', $otherId)->exists()) {
            $this->record->specialties()->updateExistingPivot($otherId, [
                'notes' => $this->data['specialty_other_note'] ?? null,
            ]);
        }
    }
}
