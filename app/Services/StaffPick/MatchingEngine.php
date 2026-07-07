<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MatchingEngine
{
    /**
     * Mean radius of the Earth in miles, used by the Haversine formula.
     */
    private const EARTH_RADIUS_MILES = 3958.7613;

    /**
     * Fallbacks used when the tenant has no config row yet (mirror the column
     * defaults on sp_tenant_configs).
     */
    private const DEFAULT_FEATHERING_MILES = 2;

    private const DEFAULT_RADIUS_MILES = 15;

    /**
     * Return the providers eligible for an intake request. Eligibility ONLY — no
     * scoring, no ordering. The returned collection is in natural query order;
     * {@see ProviderScorer} owns the final order for both the manual Find Matches
     * modal and the automated cascade.
     *
     * Hard filters (exclude entirely): active provider, matching discipline, known
     * coordinates, within radius_max_miles + feathering, exact gender match when the
     * subject states a preference, the tenant's internal/patient rating floors, and
     * language (see below).
     *
     * Language is a hard filter with a pool-level fallback: if the subject states a
     * language preference and at least one eligible provider speaks it, every
     * non-speaker is excluded. If NO eligible provider speaks it, nobody is excluded
     * on language and every result is flagged language_warning = true so staff can
     * see the preference could not be honored.
     *
     * When $radiusOverrideMiles is given (e.g. a scheduler re-trigger after a queue
     * was exhausted), it replaces every provider's own max radius as the eligibility
     * cutoff — a one-time widening of the net for this run only.
     *
     * @return Collection<int, MatchingResult>
     */
    public function match(IntakeRequest $intakeRequest, ?float $radiusOverrideMiles = null): Collection
    {
        $subject = $intakeRequest->subject;

        if ($intakeRequest->discipline_id === null
            || $subject === null
            || $subject->latitude === null
            || $subject->longitude === null) {
            return new Collection;
        }

        $subjectLat = (float) $subject->latitude;
        $subjectLng = (float) $subject->longitude;

        $config = TenantConfig::query()
            ->where('tenant_id', $intakeRequest->tenant_id)
            ->first();

        $feathering = (float) ($config?->feathering_miles ?? self::DEFAULT_FEATHERING_MILES);
        $defaultRadius = (int) ($config?->default_radius_miles ?? self::DEFAULT_RADIUS_MILES);
        $internalMin = $config?->rating_internal_min !== null ? (float) $config->rating_internal_min : null;
        $patientMin = $config?->rating_patient_min !== null ? (float) $config->rating_patient_min : null;

        $genderPreference = $subject->provider_gender_preference;
        $languagePreference = $subject->language_preference;
        $requestedProviderId = $intakeRequest->requested_provider_id !== null ? (int) $intakeRequest->requested_provider_id : null;

        $providers = Provider::query()
            ->where('tenant_id', $intakeRequest->tenant_id)
            // A provider is eligible for the case's discipline if they hold it in their
            // set — multi-discipline providers match cases in ANY discipline they hold.
            ->whereHas('disciplines', fn (Builder $query): Builder => $query->whereKey($intakeRequest->discipline_id))
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['tier', 'languages'])
            ->get();

        // First pass: apply hard filters (except language) and gather inputs.
        $eligible = new Collection;

        foreach ($providers as $provider) {
            $distance = self::distanceMiles($subjectLat, $subjectLng, (float) $provider->latitude, (float) $provider->longitude);
            $maxRadius = (float) ($provider->radius_max_miles ?: $defaultRadius);
            $cutoff = $radiusOverrideMiles !== null
                ? $radiusOverrideMiles + $feathering
                : $maxRadius + $feathering;

            $isRequested = $requestedProviderId !== null && (int) $provider->id === $requestedProviderId;

            // A referral-requested provider is always surfaced — even beyond their travel
            // radius (flagged out_of_radius so staff see it's a stretch). Every other
            // provider is hard-filtered by distance. Gender/rating gates still apply.
            if ($distance > $cutoff && ! $isRequested) {
                continue;
            }

            if (! self::matchesGender($genderPreference, $provider->gender)) {
                continue;
            }

            $internalRating = $provider->internal_rating !== null ? (float) $provider->internal_rating : null;
            $patientRating = $provider->rating_90day_avg !== null ? (float) $provider->rating_90day_avg : null;

            if (! self::passesRatingFloor($internalRating, $internalMin)) {
                continue;
            }

            if (! self::passesRatingFloor($patientRating, $patientMin)) {
                continue;
            }

            $languageMatched = self::languageMatches(
                $languagePreference,
                $provider->languages->flatMap(fn ($language): array => [$language->name, $language->code])->all(),
            );

            $eligible->push((object) [
                'provider' => $provider,
                'distance' => $distance,
                'languageMatched' => $languageMatched,
                'tierPriority' => $provider->tier?->priority ?? PHP_INT_MAX,
                'isPreferred' => (bool) $provider->is_preferred,
                'requested' => $isRequested,
                'outOfRadius' => $distance > $cutoff,
            ]);
        }

        // Language hard filter with pool-level fallback. When the preference can be
        // satisfied, drop every non-speaker. When it can't, keep everyone and warn.
        $hasPreference = filled($languagePreference);
        $anySpeaker = $eligible->contains(fn (object $row): bool => $row->languageMatched);
        $fallbackFired = $hasPreference && $eligible->isNotEmpty() && ! $anySpeaker;

        if ($hasPreference && $anySpeaker) {
            $eligible = $eligible->filter(fn (object $row): bool => $row->languageMatched);
        }

        return $eligible
            ->map(fn (object $row): MatchingResult => new MatchingResult(
                provider: $row->provider,
                distanceMiles: $row->distance,
                languageMatched: $row->languageMatched,
                languageWarning: $fallbackFired,
                factors: [
                    'requested' => $row->requested,
                    'out_of_radius' => $row->outOfRadius,
                    'is_preferred' => $row->isPreferred,
                    'tier_priority' => $row->tierPriority,
                ],
            ))
            ->values();
    }

    /**
     * Great-circle distance between two lat/lng points, in miles (Haversine).
     */
    public static function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_MILES * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Hard gender filter: passes when the subject states no preference, otherwise
     * the provider's gender must match it exactly (case-insensitive). A provider
     * with no recorded gender never satisfies a stated preference — no fallback.
     */
    public static function matchesGender(?string $preference, ?string $providerGender): bool
    {
        $preference = trim((string) $preference);

        if ($preference === '') {
            return true;
        }

        if ($providerGender === null) {
            return false;
        }

        return mb_strtolower($preference) === mb_strtolower(trim($providerGender));
    }

    /**
     * Rating floor: passes when the tenant sets no floor, or the provider is
     * unrated (null), or the provider's rating meets/exceeds the floor.
     */
    public static function passesRatingFloor(?float $rating, ?float $floor): bool
    {
        if ($floor === null || $rating === null) {
            return true;
        }

        return $rating >= $floor;
    }

    /**
     * True when the subject's language preference matches one of the provider's
     * language names or ISO codes (case-insensitive). No preference → false.
     *
     * @param  array<string>  $providerLanguages
     */
    public static function languageMatches(?string $preference, array $providerLanguages): bool
    {
        $preference = trim((string) $preference);

        if ($preference === '') {
            return false;
        }

        $needle = mb_strtolower($preference);
        $haystack = array_map(fn (string $language): string => mb_strtolower($language), $providerLanguages);

        return in_array($needle, $haystack, true);
    }
}
