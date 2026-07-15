<?php

namespace App\Jobs\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\Tenant;
use App\Services\StaffPick\MatchDispatchService;
use App\Services\StaffPick\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sweep MATCH_SENT cases whose response window has elapsed and cascade them to the next
 * provider. Scheduled every 5 minutes — replaces CheckOfferExpiry. Runs one scoped sweep
 * per tenant inside a {@see TenantContext}, so every query (cases, offers, the guarded
 * dispatch re-check) is isolated to that tenant by construction rather than by relying on
 * globally-unique ids.
 */
class ProcessMatchTimeouts implements ShouldQueue
{
    use Queueable;

    public function handle(MatchDispatchService $dispatch, TenantContext $context): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => $context->run($tenant, function () use ($dispatch): void {
            IntakeRequest::query()
                ->where('status', IntakeRequest::STATUS_MATCH_SENT)
                ->whereNotNull('last_match_sent_at')
                ->get()
                ->each(function (IntakeRequest $case) use ($dispatch): void {
                    $offer = $case->assignmentOffers()
                        ->where('status', AssignmentOffer::STATUS_PENDING)
                        ->whereNotNull('offered_at')
                        ->latest('offered_at')
                        ->first();

                    $window = $offer?->response_window_minutes ?? 30;

                    if ($case->last_match_sent_at->diffInMinutes(now()) >= $window) {
                        $dispatch->handleTimeout($case);
                    }
                });
        }));
    }
}
