<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use Illuminate\Support\Collection;

class MatchingEngine
{
    /**
     * Mean radius of the Earth in miles, used by the Haversine formula.
     */
    private const EARTH_RADIUS_MILES = 3958.7613;

    /**
     * Additive bonus weights applied on top of the (required) distance score.
     * Tunable — these decide how much a specialty/language match lifts a provider
     * relative to a closer-but-otherwise-equal provider.
     */
    public const SPECIALTY_BONUS = 0.25;

    public const LANGUAGE_BONUS = 0.15;

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
     * coordinates, and within radius_max_miles + feathering of the subject. The
     * surviving providers are ordered strictly by tier (priority ascending), then
     * by quality score descending within each tier.
     *
     * @return Collection<int, MatchingResult>
     */
    public function match(IntakeRequest $intakeRequest): Collection
    {
        $subject = $intakeRequest->subject;

        // Discipline and the subject's coordinates are required to match at all.
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

        $requestedSpecialtyIds = $intakeRequest->specialties->pluck('id')->all();
        $languagePreference = $subject->language_preference;

        return Provider::query()
            ->where('tenant_id', $intakeRequest->tenant_id)
            ->where('discipline_id', $intakeRequest->discipline_id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['tier', 'specialties', 'languages'])
            ->get()
            ->map(fn (Provider $provider): ?MatchingResult => $this->score(
                $provider,
                $subjectLat,
                $subjectLng,
                $requestedSpecialtyIds,
                $languagePreference,
                $feathering,
                $defaultRadius,
            ))
            ->filter()
            ->sort(function (MatchingResult $a, MatchingResult $b): int {
                // Strict tiering first (lower priority number wins), then quality.
                return ($a->factors['tier_priority'] <=> $b->factors['tier_priority'])
                    ?: ($b->score <=> $a->score);
            })
            ->values();
    }

    /**
     * Score a single provider, or return null when it fails the distance filter.
     *
     * @param  array<int>  $requestedSpecialtyIds
     */
    private function score(
        Provider $provider,
        float $subjectLat,
        float $subjectLng,
        array $requestedSpecialtyIds,
        ?string $languagePreference,
        float $feathering,
        int $defaultRadius,
    ): ?MatchingResult {
        $distance = self::distanceMiles(
            $subjectLat,
            $subjectLng,
            (float) $provider->latitude,
            (float) $provider->longitude,
        );

        $maxRadius = (float) ($provider->radius_max_miles ?: $defaultRadius);
        $cutoff = $maxRadius + $feathering;

        if ($distance > $cutoff) {
            return null;
        }

        $distanceScore = self::scoreDistance($distance, $cutoff);
        $specialtyScore = self::scoreSpecialty($requestedSpecialtyIds, $provider->specialties->pluck('id')->all());
        $languageScore = self::scoreLanguage(
            $languagePreference,
            $provider->languages->flatMap(fn ($language): array => [$language->name, $language->code])->all(),
        );

        $factors = [
            'tier_priority' => $provider->tier?->priority ?? PHP_INT_MAX,
            'distance_score' => round($distanceScore, 4),
            'specialty' => $specialtyScore,
            'language' => $languageScore === 1.0,
            'near_miss' => $distance > $maxRadius,
            'acceptance' => null, // stub: historical acceptance rate (factor 7, future)
        ];

        return new MatchingResult(
            $provider,
            self::composeScore($distanceScore, $specialtyScore, $languageScore),
            $distance,
            $factors,
        );
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
     * Fraction of the requested specialties the provider covers, in [0, 1].
     * Returns 0 when no specialties were requested (no bonus, no penalty).
     *
     * @param  array<int>  $requestedSpecialtyIds
     * @param  array<int>  $providerSpecialtyIds
     */
    public static function scoreSpecialty(array $requestedSpecialtyIds, array $providerSpecialtyIds): float
    {
        if ($requestedSpecialtyIds === []) {
            return 0.0;
        }

        $matched = count(array_intersect($requestedSpecialtyIds, $providerSpecialtyIds));

        return $matched / count($requestedSpecialtyIds);
    }

    /**
     * 1.0 when the subject's language preference matches one of the provider's
     * language names or ISO codes (case-insensitive), else 0.0. No preference → 0.
     *
     * @param  array<string>  $providerLanguages  provider language names and codes
     */
    public static function scoreLanguage(?string $subjectPreference, array $providerLanguages): float
    {
        $preference = trim((string) $subjectPreference);

        if ($preference === '') {
            return 0.0;
        }

        $needle = mb_strtolower($preference);
        $haystack = array_map(fn (string $language): string => mb_strtolower($language), $providerLanguages);

        return in_array($needle, $haystack, true) ? 1.0 : 0.0;
    }

    /**
     * Blend the (required) distance score with the additive specialty and language
     * bonuses into a single quality score used to rank within a tier.
     */
    public static function composeScore(float $distanceScore, float $specialtyScore, float $languageScore): float
    {
        return $distanceScore
            + (self::SPECIALTY_BONUS * $specialtyScore)
            + (self::LANGUAGE_BONUS * $languageScore);
    }
}
