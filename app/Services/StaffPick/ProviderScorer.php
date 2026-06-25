<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use Illuminate\Support\Collection;

/**
 * Orders already-eligible providers for a case, best-first.
 *
 * Eligibility (hard filters: discipline, radius, gender, rating floors, requested-
 * provider bypass) is owned by {@see MatchingEngine}. This interface owns ordering
 * ONLY — it must never re-filter the set — so the real weighted scoring model can be
 * swapped in for the placeholder without touching the cascade engine.
 */
interface ProviderScorer
{
    /**
     * @param  Collection<int, Provider>  $eligible  providers already passed MatchingEngine
     * @return Collection<int, Provider> the same providers, ordered best-first
     */
    public function order(IntakeRequest $case, Collection $eligible): Collection;
}
