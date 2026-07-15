<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    public function __construct(
        private SlackNotificationService $slack,
    ) {}

    /**
     * Manually assign an intake request to a provider: supersede any current assignment,
     * create a new (manual) assignment, and move the case to matched with this provider
     * as lead clinician. A direct staff override that skips the offer cascade.
     */
    public function assign(IntakeRequest $intakeRequest, Provider $provider): Assignment
    {
        $assignment = DB::transaction(function () use ($intakeRequest, $provider): Assignment {
            // Withdraw any offer still out on THIS case, or a later accept of it would
            // silently clobber this manual placement (accept paths already refuse a
            // withdrawn offer, so withdrawing is what closes the hole).
            $intakeRequest->assignmentOffers()
                ->where('status', AssignmentOffer::STATUS_PENDING)
                ->update(['status' => AssignmentOffer::STATUS_WITHDRAWN, 'responded_at' => now()]);

            $intakeRequest->assignments()
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $assignment = Assignment::create([
                'tenant_id' => $intakeRequest->tenant_id,
                'intake_request_id' => $intakeRequest->id,
                'provider_id' => $provider->id,
                'status' => Assignment::STATUS_OFFERED,
                'is_current' => true,
                'is_manual' => true,
                'offered_at' => now(),
            ]);

            $intakeRequest->update([
                'status' => IntakeRequest::STATUS_MATCHED,
                'lead_clinician_id' => $provider->id,
                'current_match_provider_id' => null, // no longer mid-cascade
                'assigned_at' => now(),
            ]);

            return $assignment;
        });

        $this->slack->notifyProviderAssigned($assignment); // outside txn — external call

        return $assignment;
    }
}
