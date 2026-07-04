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
     * Bonus added to a provider's score for matching the subject's language
     * preference. Deliberately heavy — greater than the max proximity score (1.0)
     * so a language match always outranks a closer non-match within the same
     * tier. Tunable.
     */
    public const LANGUAGE_BONUS = 2.0;

    /**
     * Fallbacks used when the tenant has no config row yet (mirror the column
     * defaults on sp_tenant_configs).
     */
    private const DEFAULT_FEATHERING_MILES = 2;

    private const DEFAULT_RADIUS_MILES = 15;

    /**
     * Rank the eligible providers for an intake request, best first.
     *
     * Hard filters (exclude entirely): active provider, matching discipline, known
     * coordinates, within radius_max_miles + feathering, exact gender match when
     * the subject states a preference, and the tenant's internal/patient rating
     * floors. Survivors are ordered: preferred providers first, then by tier
     * (priority ascending), then by score (language match + proximity) descending.
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
            // Scoring is unchanged: they score exactly as a single-discipline provider
            // would for this discipline (no bonus or penalty for holding several).
            ->whereHas('disciplines', fn (Builder $query): Builder => $query->whereKey($intakeRequest->discipline_id))
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['tier', 'languages'])
            ->get();

        // First pass: apply hard filters and gather the scoring inputs.
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

            $distanceScore = self::scoreDistance($distance, $cutoff);

            $eligible->push((object) [
                'provider' => $provider,
                'distance' => $distance,
                'distanceScore' => $distanceScore,
                'languageMatched' => $languageMatched,
                'score' => self::composeScore($distanceScore, $languageMatched),
                'tierPriority' => $provider->tier?->priority ?? PHP_INT_MAX,
                'isPreferred' => (bool) $provider->is_preferred,
                'outOfRadius' => $distance > $cutoff,
            ]);
        }

        // The whole result set warns when a language was requested but nobody speaks it.
        $languageWarning = filled($languagePreference)
            && $eligible->isNotEmpty()
            && ! $eligible->contains(fn (object $row): bool => $row->languageMatched);

        return $eligible
            ->map(fn (object $row): MatchingResult => new MatchingResult(
                provider: $row->provider,
                score: $row->score,
                distanceMiles: $row->distance,
                languageMatched: $row->languageMatched,
                languageWarning: $languageWarning,
                factors: [
                    'requested' => $requestedProviderId !== null && $row->provider->id === $requestedProviderId,
                    'out_of_radius' => $row->outOfRadius,
                    'is_preferred' => $row->isPreferred,
                    'tier_priority' => $row->tierPriority,
                    'distance_score' => round($row->distanceScore, 4),
                    'language' => $row->languageMatched,
                ],
            ))
            ->sort(function (MatchingResult $a, MatchingResult $b): int {
                // Referral-requested provider first, then preferred, then tier ascending,
                // then score descending. (Requested still flows through the normal offer
                // queue — surfacing only changes order, not the pipeline.)
                return ($b->factors['requested'] <=> $a->factors['requested'])
                    ?: (($b->factors['is_preferred'] <=> $a->factors['is_preferred'])
                        ?: (($a->factors['tier_priority'] <=> $b->factors['tier_priority'])
                            ?: ($b->score <=> $a->score)));
            })
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
     * Proximity score in [0, 1]: 1 at the subject's doorstep, falling linearly to
     * 0 at the eligibility cutoff (provider max radius + feathering), clamped.
     */
    public static function scoreDistance(float $distanceMiles, float $cutoffMiles): float
    {
        if ($cutoffMiles <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, 1.0 - ($distanceMiles / $cutoffMiles)));
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

    /**
     * Blend the proximity score with a heavy bonus for a language match.
     */
    public static function composeScore(float $distanceScore, bool $languageMatched): float
    {
        return $distanceScore + ($languageMatched ? self::LANGUAGE_BONUS : 0.0);
    }
}
