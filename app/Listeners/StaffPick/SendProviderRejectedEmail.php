<?php

namespace App\Listeners\StaffPick;

use App\Events\StaffPick\ProviderRejected;
use App\Mail\StaffPick\ProviderRejected as ProviderRejectedMail;
use App\Models\Tenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendProviderRejectedEmail implements ShouldDispatchAfterCommit, ShouldQueue
{
    public function handle(ProviderRejected $event): void
    {
        $provider = $event->provider;

        if (! filled($provider->email)) {
            return;
        }

        $tenant = Tenant::query()->find($provider->tenant_id);

        if ($tenant === null) {
            return;
        }

        Mail::to($provider->email)->queue(new ProviderRejectedMail($provider, $tenant, $event->reason));
    }
}
