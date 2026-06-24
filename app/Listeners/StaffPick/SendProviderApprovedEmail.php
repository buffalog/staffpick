<?php

namespace App\Listeners\StaffPick;

use App\Events\StaffPick\ProviderApproved;
use App\Mail\StaffPick\ProviderApproved as ProviderApprovedMail;
use App\Models\Tenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendProviderApprovedEmail implements ShouldDispatchAfterCommit, ShouldQueue
{
    public function handle(ProviderApproved $event): void
    {
        $provider = $event->provider;

        if (! filled($provider->email)) {
            return;
        }

        $tenant = Tenant::query()->find($provider->tenant_id);

        if ($tenant === null) {
            return;
        }

        Mail::to($provider->email)->queue(new ProviderApprovedMail($provider, $tenant));
    }
}
