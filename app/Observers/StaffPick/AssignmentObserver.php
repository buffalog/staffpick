<?php

namespace App\Observers\StaffPick;

use App\Jobs\StaffPick\SendProviderSurvey;
use App\Models\StaffPick\Assignment;

class AssignmentObserver
{
    /**
     * An assignment created already in the completed state still warrants a survey.
     */
    public function created(Assignment $assignment): void
    {
        if ($assignment->status === Assignment::STATUS_COMPLETED) {
            SendProviderSurvey::dispatch($assignment->id);
        }
    }

    /**
     * Fire the survey when an assignment transitions into the completed state.
     */
    public function updated(Assignment $assignment): void
    {
        if ($assignment->wasChanged('status') && $assignment->status === Assignment::STATUS_COMPLETED) {
            SendProviderSurvey::dispatch($assignment->id);
        }
    }
}
