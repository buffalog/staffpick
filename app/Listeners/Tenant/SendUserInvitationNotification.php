<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\UserInvitedToTenant;
use App\Mail\Tenant\UserInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendUserInvitationNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserInvitedToTenant $event): void
    {
        // Delivery must not break invitation creation: on the sync queue this
        // listener runs inline in the web request, so an SMTP/transport failure
        // would otherwise 500 the create page. Log and move on — the invitation
        // record is already persisted and can be re-sent.
        try {
            Mail::to($event->invitation->email)
                ->send(new UserInvitation($event->invitation));
        } catch (Throwable $e) {
            Log::error('Failed to send user invitation email', [
                'invitation_id' => $event->invitation->id,
                'email' => $event->invitation->email,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
