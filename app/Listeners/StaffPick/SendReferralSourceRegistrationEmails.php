<?php

namespace App\Listeners\StaffPick;

use App\Events\StaffPick\ReferralSourceRegistered;
use App\Mail\StaffPick\ReferralSourceRegisteredApplicant;
use App\Mail\StaffPick\ReferralSourceRegisteredStaff;
use App\Models\Tenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendReferralSourceRegistrationEmails implements ShouldDispatchAfterCommit, ShouldQueue
{
    public function handle(ReferralSourceRegistered $event): void
    {
        $source = $event->source;

        $staffEmail = config('staffpick.notifications.staff_email') ?: config('mail.from.address');

        if (filled($staffEmail)) {
            Mail::to($staffEmail)->queue(new ReferralSourceRegisteredStaff($source));
        }

        if (filled($source->email)) {
            $tenant = Tenant::query()->find($source->tenant_id);

            if ($tenant !== null) {
                Mail::to($source->email)->queue(new ReferralSourceRegisteredApplicant($source, $tenant));
            }
        }
    }
}
