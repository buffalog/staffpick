<?php

namespace App\Listeners\StaffPick;

use App\Events\StaffPick\ReferralSourceRejected;
use App\Mail\StaffPick\ReferralSourceRejected as ReferralSourceRejectedMail;
use App\Models\Tenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendReferralSourceRejectedEmail implements ShouldDispatchAfterCommit, ShouldQueue
{
    public function handle(ReferralSourceRejected $event): void
    {
        $source = $event->source;

        if (! filled($source->email)) {
            return;
        }

        $tenant = Tenant::query()->find($source->tenant_id);

        if ($tenant === null) {
            return;
        }

        Mail::to($source->email)->queue(new ReferralSourceRejectedMail($source, $tenant, $event->reason));
    }
}
