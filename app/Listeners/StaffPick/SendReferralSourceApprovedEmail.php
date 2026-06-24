<?php

namespace App\Listeners\StaffPick;

use App\Events\StaffPick\ReferralSourceApproved;
use App\Mail\StaffPick\ReferralSourceApproved as ReferralSourceApprovedMail;
use App\Models\Tenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendReferralSourceApprovedEmail implements ShouldDispatchAfterCommit, ShouldQueue
{
    public function handle(ReferralSourceApproved $event): void
    {
        $source = $event->source;

        if (! filled($source->email)) {
            return;
        }

        $tenant = Tenant::query()->find($source->tenant_id);

        if ($tenant === null) {
            return;
        }

        Mail::to($source->email)->queue(new ReferralSourceApprovedMail($source, $tenant));
    }
}
