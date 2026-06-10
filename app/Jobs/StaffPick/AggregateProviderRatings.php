<?php

namespace App\Jobs\StaffPick;

use App\Services\StaffPick\ProviderRatingAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Weekly job: recompute every provider's rolling patient ratings and raise any
 * pending tier-change reviews. Scheduled Sunday nights in routes/console.php.
 */
class AggregateProviderRatings implements ShouldQueue
{
    use Queueable;

    public function handle(ProviderRatingAggregator $aggregator): void
    {
        $aggregator->aggregate();
    }
}
