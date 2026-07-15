<?php

namespace App\Services\StaffPick;

use App\Jobs\StaffPick\AggregateProviderRatings;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderRatingReview;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\TenantConfig;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Recomputes rolling patient-survey ratings for providers and raises pending
 * tier-change reviews when a provider crosses the tenant's promotion/demotion
 * thresholds. Driven weekly by {@see AggregateProviderRatings}.
 */
class ProviderRatingAggregator
{
    /**
     * Recompute ratings for every active provider in the current tenant (the Provider query
     * auto-scopes to the active {@see TenantContext}; {@see AggregateProviderRatings} drives
     * one pass per tenant).
     */
    public function aggregate(?DateTimeInterface $asOf = null): int
    {
        $asOf = $asOf ? CarbonImmutable::instance($asOf) : CarbonImmutable::now();
        $count = 0;

        Provider::query()
            ->where('is_active', true)
            ->with('tier')
            ->chunkById(200, function ($providers) use ($asOf, &$count): void {
                foreach ($providers as $provider) {
                    $this->aggregateProvider($provider, $asOf);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Recompute the rolling averages for one provider and, if it crosses a
     * threshold with enough surveys, raise a pending tier-change review.
     */
    public function aggregateProvider(Provider $provider, DateTimeInterface $asOf): void
    {
        $asOf = CarbonImmutable::instance($asOf);

        [$avg90, $count90] = $this->windowStats($provider, $asOf, 90);
        [$avg180, $count180] = $this->windowStats($provider, $asOf, 180);

        $provider->forceFill([
            'rating_90day_avg' => $avg90,
            'rating_survey_count_90day' => $count90,
            'rating_180day_avg' => $avg180,
            'rating_survey_count_180day' => $count180,
        ])->save();

        $this->maybeRaiseReview($provider, $asOf, $avg90, $count90, $avg180);
    }

    /**
     * Average rating and response count over the trailing $days window.
     *
     * @return array{0: float|null, 1: int}
     */
    private function windowStats(Provider $provider, CarbonImmutable $asOf, int $days): array
    {
        $since = $asOf->subDays($days);

        // Average in PHP, not via SQL AVG(): SQL Server's AVG() on an integer
        // column does integer division (AVG(5,4) = 4, not 4.5).
        $ratings = ProviderSurvey::query()
            ->where('provider_id', $provider->id)
            ->where('status', ProviderSurvey::STATUS_RESPONDED)
            ->whereNotNull('rating')
            ->where('responded_at', '>=', $since)
            ->pluck('rating');

        if ($ratings->isEmpty()) {
            return [null, 0];
        }

        return [round((float) $ratings->avg(), 2), $ratings->count()];
    }

    private function maybeRaiseReview(
        Provider $provider,
        CarbonImmutable $asOf,
        ?float $avg90,
        int $count90,
        ?float $avg180,
    ): void {
        if ($provider->tier_id === null || $avg90 === null) {
            return;
        }

        $config = TenantConfig::query()->where('tenant_id', $provider->tenant_id)->first();

        $promotionThreshold = (float) ($config?->rating_promotion_threshold ?? 4.50);
        $demotionThreshold = (float) ($config?->rating_demotion_threshold ?? 3.00);
        $minSurveys = (int) ($config?->rating_min_survey_count ?? 10);

        if ($count90 < $minSurveys) {
            return;
        }

        $reviewType = match (true) {
            $avg90 >= $promotionThreshold => ProviderRatingReview::TYPE_PROMOTION,
            $avg90 <= $demotionThreshold => ProviderRatingReview::TYPE_DEMOTION,
            default => null,
        };

        if ($reviewType === null) {
            return;
        }

        [$start, $end] = $this->reviewPeriod($asOf, $config?->rating_review_period ?? 'quarterly');

        $alreadyOpen = ProviderRatingReview::query()
            ->where('provider_id', $provider->id)
            ->where('status', ProviderRatingReview::STATUS_PENDING)
            ->whereDate('review_period_start', $start->toDateString())
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        ProviderRatingReview::create([
            'tenant_id' => $provider->tenant_id,
            'provider_id' => $provider->id,
            'review_type' => $reviewType,
            'current_tier_id' => $provider->tier_id,
            'suggested_tier_id' => $this->suggestedTier($provider, $reviewType)?->id,
            'rating_90day_avg' => $avg90,
            'rating_180day_avg' => $avg180,
            'survey_count' => $count90,
            'review_period_start' => $start->toDateString(),
            'review_period_end' => $end->toDateString(),
            'status' => ProviderRatingReview::STATUS_PENDING,
        ]);
    }

    /**
     * The adjacent tier in the direction of the review (lower priority number for
     * a promotion, higher for a demotion); null if there is no tier to move to.
     */
    private function suggestedTier(Provider $provider, string $reviewType): ?ProviderTier
    {
        $currentPriority = $provider->tier?->priority;

        if ($currentPriority === null) {
            return null;
        }

        $query = ProviderTier::query()
            ->where('tenant_id', $provider->tenant_id)
            ->where('is_active', true);

        if ($reviewType === ProviderRatingReview::TYPE_PROMOTION) {
            return $query->where('priority', '<', $currentPriority)->orderByDesc('priority')->first();
        }

        return $query->where('priority', '>', $currentPriority)->orderBy('priority')->first();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function reviewPeriod(CarbonImmutable $asOf, string $period): array
    {
        if ($period === 'biannual') {
            return $asOf->month <= 6
                ? [$asOf->startOfYear(), $asOf->setDate($asOf->year, 6, 30)->endOfDay()]
                : [$asOf->setDate($asOf->year, 7, 1)->startOfDay(), $asOf->endOfYear()];
        }

        return [$asOf->firstOfQuarter(), $asOf->lastOfQuarter()];
    }
}
