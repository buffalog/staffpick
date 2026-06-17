<?php

namespace App\Jobs\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Services\StaffPick\OfferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the ranked assignment-offer queue for an intake and sends the first offer.
 * An optional radius override widens the matching net (scheduler re-trigger).
 */
class DispatchOffers implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $intakeRequestId,
        public ?float $radiusOverrideMiles = null,
    ) {}

    public function handle(OfferService $offers): void
    {
        $intake = IntakeRequest::find($this->intakeRequestId);

        if ($intake === null) {
            return;
        }

        $offers->dispatchOffers($intake, $this->radiusOverrideMiles);
    }
}
