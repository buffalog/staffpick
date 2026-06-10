<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\AggregateProviderRatings;
use App\Services\StaffPick\ProviderRatingAggregator;
use Illuminate\Console\Scheduling\Schedule;
use Mockery;
use Tests\TestCase;

class AggregateProviderRatingsJobTest extends TestCase
{
    public function test_the_job_runs_the_rating_aggregator(): void
    {
        $aggregator = Mockery::mock(ProviderRatingAggregator::class);
        $aggregator->shouldReceive('aggregate')->once();

        (new AggregateProviderRatings)->handle($aggregator);
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
