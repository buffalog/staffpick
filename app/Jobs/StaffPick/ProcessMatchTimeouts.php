<?php

namespace App\Jobs\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Services\StaffPick\MatchDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sweep MATCH_SENT cases whose response window has elapsed and cascade them to the next
 * provider. Scheduled every 5 minutes — replaces CheckOfferExpiry. Runs with no Filament
 * tenant context, so the BelongsToTenant scope is a no-op and it sweeps all tenants.
 */
class ProcessMatchTimeouts implements ShouldQueue
{
    use Queueable;

    public function handle(MatchDispatchService $dispatch): void
    {
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
    }
}
