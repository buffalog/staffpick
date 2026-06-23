<?php

namespace App\Events\Tenant;

use App\Models\Invitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when an invitation is created. Implements ShouldDispatchAfterCommit so
 * the notification listener only fires once the surrounding DB transaction commits:
 * Filament wraps record creation in a transaction, and dispatching inline meant a
 * failing (sync) mail listener rolled the invitation insert back. Deferring to
 * after-commit guarantees the invitation persists regardless of delivery outcome.
 */
class UserInvitedToTenant implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Invitation $invitation,
    ) {
        //
    }
}
