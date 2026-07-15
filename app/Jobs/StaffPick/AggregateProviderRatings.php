<?php

namespace App\Jobs\StaffPick;

use App\Models\Tenant;
use App\Services\StaffPick\ProviderRatingAggregator;
use App\Services\StaffPick\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Weekly job: recompute every provider's rolling patient ratings and raise any
 * pending tier-change reviews. Scheduled Sunday nights in routes/console.php. Runs one
 * scoped pass per tenant inside a {@see TenantContext} so the aggregator's Provider query
 * isolates to each tenant.
 */
class AggregateProviderRatings implements ShouldQueue
{
    use Queueable;

    public function handle(ProviderRatingAggregator $aggregator, TenantContext $context): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => $context->run($tenant, fn () => $aggregator->aggregate()));
    }
}
