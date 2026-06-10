<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;

class AssignmentService
{
    /**
     * Offer an intake request to a provider: supersede any current assignment,
     * create a new (manual) offer, and move the case to assigned_pending.
     */
    public function assign(IntakeRequest $intakeRequest, Provider $provider): Assignment
    {
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
            'status' => 'assigned_pending',
            'assigned_at' => now(),
        ]);

        return $assignment;
    }
}
