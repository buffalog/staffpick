<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\AggregateProviderRatings;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\Tenant;
use App\Services\StaffPick\ProviderRatingAggregator;
use App\Services\StaffPick\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Feature\FeatureTest;

class AggregateProviderRatingsJobTest extends FeatureTest
{
    private function activeProvider(Tenant $tenant): Provider
    {
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);

        return Provider::factory()->create([
            'tenant_id' => $tenant->id,
            'discipline_id' => $discipline->id,
            'is_active' => true,
        ]);
    }

    private function respondedSurvey(Provider $provider, int $rating): void
    {
        ProviderSurvey::factory()
            ->responded($rating, now()->subDays(10)) // inside the 90-day window
            ->create([
                'tenant_id' => $provider->tenant_id,
                'provider_id' => $provider->id,
                'subject_id' => 1,
                'assignment_id' => 1,
                'status' => ProviderSurvey::STATUS_RESPONDED,
            ]);
    }

    public function test_the_job_aggregates_ratings_per_tenant_in_isolation(): void
    {
        // Two tenants, each with an active provider and a distinctly-rated responded survey.
        $tenantA = $this->createTenant();
        $providerA = $this->activeProvider($tenantA);
        $this->respondedSurvey($providerA, 5);

        $tenantB = $this->createTenant();
        $providerB = $this->activeProvider($tenantB);
        $this->respondedSurvey($providerB, 2);

        (new AggregateProviderRatings)->handle(app(ProviderRatingAggregator::class), app(TenantContext::class));

        // Each provider's rating reflects ONLY its own tenant's survey.
        $this->assertSame(5.0, (float) $providerA->refresh()->rating_90day_avg);
        $this->assertSame(1, $providerA->rating_survey_count_90day);
        $this->assertSame(2.0, (float) $providerB->refresh()->rating_90day_avg);
        $this->assertSame(1, $providerB->rating_survey_count_90day);
    }

    public function test_the_aggregation_job_is_scheduled_weekly(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue(
            $events->contains(fn ($event): bool => str_contains(
                $event->getSummaryForDisplay().' '.(string) $event->description,
                'staffpick-aggregate-provider-ratings',
            )),
            'Expected the provider rating aggregation job to be scheduled.',
        );
    }
}
