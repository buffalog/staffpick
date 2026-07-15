<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderRatingReview;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\StaffPick\ProviderRatingAggregator;
use App\Services\StaffPick\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Feature\FeatureTest;

class ProviderRatingAggregatorTest extends FeatureTest
{
    private CarbonImmutable $asOf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asOf = CarbonImmutable::parse('2026-06-07 12:00:00'); // a Sunday in Q2
    }

    private function aggregator(): ProviderRatingAggregator
    {
        return app(ProviderRatingAggregator::class);
    }

    /** Aggregate in the provider's tenant context — it reads ProviderSurvey (PHI). */
    private function aggregateScoped(Provider $provider): void
    {
        app(TenantContext::class)->run($provider->tenant, fn () => $this->aggregator()->aggregateProvider($provider, $this->asOf));
    }

    private function provider(Tenant $tenant, array $attributes = []): Provider
    {
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);

        return Provider::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'discipline_id' => $discipline->id,
        ], $attributes));
    }

    private function survey(Provider $provider, int $rating, int $daysAgo, string $status = ProviderSurvey::STATUS_RESPONDED): ProviderSurvey
    {
        return ProviderSurvey::factory()
            ->responded($rating, $this->asOf->subDays($daysAgo))
            ->create([
                'tenant_id' => $provider->tenant_id,
                'provider_id' => $provider->id,
                'subject_id' => 1,
                'assignment_id' => 1,
                'status' => $status,
            ]);
    }

    public function test_computes_90_and_180_day_averages_and_counts(): void
    {
        $tenant = $this->createTenant();
        $provider = $this->provider($tenant);

        $this->survey($provider, 5, 10);  // in 90 + 180
        $this->survey($provider, 4, 20);  // in 90 + 180
        $this->survey($provider, 3, 100); // in 180 only
        $this->survey($provider, 1, 200); // outside both

        $this->aggregateScoped($provider);
        $provider->refresh();

        $this->assertSame(4.5, (float) $provider->rating_90day_avg);
        $this->assertSame(2, $provider->rating_survey_count_90day);
        $this->assertSame(4.0, (float) $provider->rating_180day_avg);
        $this->assertSame(3, $provider->rating_survey_count_180day);
    }

    public function test_only_responded_surveys_count(): void
    {
        $tenant = $this->createTenant();
        $provider = $this->provider($tenant);

        $this->survey($provider, 5, 10);
        $this->survey($provider, 1, 10, ProviderSurvey::STATUS_PENDING); // pending: ignored

        $this->aggregateScoped($provider);
        $provider->refresh();

        $this->assertSame(5.0, (float) $provider->rating_90day_avg);
        $this->assertSame(1, $provider->rating_survey_count_90day);
    }

    public function test_creates_pending_promotion_review_when_crossing_threshold(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'rating_promotion_threshold' => 4.50,
            'rating_min_survey_count' => 2,
        ]);
        $gold = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Gold', 'priority' => 1]);
        $silver = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Silver', 'priority' => 2]);

        $provider = $this->provider($tenant, ['tier_id' => $silver->id]);
        $this->survey($provider, 5, 5);
        $this->survey($provider, 5, 8);

        $this->aggregateScoped($provider);

        $review = ProviderRatingReview::where('provider_id', $provider->id)->first();
        $this->assertNotNull($review);
        $this->assertSame(ProviderRatingReview::TYPE_PROMOTION, $review->review_type);
        $this->assertSame(ProviderRatingReview::STATUS_PENDING, $review->status);
        $this->assertSame($silver->id, (int) $review->current_tier_id);
        $this->assertSame($gold->id, $review->suggested_tier_id);
    }

    public function test_creates_demotion_review_below_threshold(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'rating_demotion_threshold' => 3.00,
            'rating_min_survey_count' => 2,
        ]);
        $gold = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Gold', 'priority' => 1]);
        $silver = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Silver', 'priority' => 2]);

        $provider = $this->provider($tenant, ['tier_id' => $gold->id]);
        $this->survey($provider, 2, 5);
        $this->survey($provider, 3, 8);

        $this->aggregateScoped($provider);

        $review = ProviderRatingReview::where('provider_id', $provider->id)->first();
        $this->assertNotNull($review);
        $this->assertSame(ProviderRatingReview::TYPE_DEMOTION, $review->review_type);
        $this->assertSame($silver->id, (int) $review->suggested_tier_id);
    }

    public function test_does_not_create_review_below_min_survey_count(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'rating_promotion_threshold' => 4.50,
            'rating_min_survey_count' => 10,
        ]);
        $silver = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Silver', 'priority' => 2]);

        $provider = $this->provider($tenant, ['tier_id' => $silver->id]);
        $this->survey($provider, 5, 5);
        $this->survey($provider, 5, 8); // only 2 < min 10

        $this->aggregateScoped($provider);

        $this->assertSame(0, ProviderRatingReview::where('provider_id', $provider->id)->count());
    }

    public function test_does_not_duplicate_review_for_the_same_period(): void
    {
        $tenant = $this->createTenant();
        TenantConfig::create([
            'tenant_id' => $tenant->id,
            'rating_promotion_threshold' => 4.50,
            'rating_min_survey_count' => 2,
        ]);
        ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Gold', 'priority' => 1]);
        $silver = ProviderTier::create(['tenant_id' => $tenant->id, 'name' => 'Silver', 'priority' => 2]);

        $provider = $this->provider($tenant, ['tier_id' => $silver->id]);
        $this->survey($provider, 5, 5);
        $this->survey($provider, 5, 8);

        $this->aggregateScoped($provider);
        $this->aggregateScoped($provider);

        $this->assertSame(1, ProviderRatingReview::where('provider_id', $provider->id)->count());
    }
}
