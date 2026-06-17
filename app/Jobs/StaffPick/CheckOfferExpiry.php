<?php

namespace App\Jobs\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Services\StaffPick\OfferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sweeps for sent offers whose window has elapsed, expires them, and advances each
 * intake to the next provider in its queue. Scheduled every minute. Runs outside any
 * Filament tenant, so the BelongsToTenant scope is a no-op and all tenants are swept.
 */
class CheckOfferExpiry implements ShouldQueue
{
    use Queueable;

    public function handle(OfferService $offers): void
    {
        AssignmentOffer::query()
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->whereNotNull('offered_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->get()
            ->each(fn (AssignmentOffer $offer) => $offers->expireOffer($offer));
    }
}
