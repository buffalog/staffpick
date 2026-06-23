<?php

namespace App\Http\Controllers;

use App\Constants\InvitationStatus;
use App\Models\Invitation;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;

class InvitationController extends Controller
{
    public function index()
    {
        return view('invitations.index');
    }

    /**
     * Accept a tenant invitation from the emailed token link, then drop the user
     * into the panel that matches the roles they were just granted. Auth-gated:
     * a guest is bounced to login and returned here afterward.
     */
    public function accept(string $token, TenantService $tenantService): RedirectResponse
    {
        // Status + expiry filtered in SQL (mirrors getUserInvitations) so we never
        // read the date column into PHP.
        $invitation = Invitation::query()
            ->where('token', $token)
            ->where('status', InvitationStatus::PENDING->value)
            ->where('expires_at', '>=', now())
            ->with('tenant')
            ->first();

        $user = auth()->user();

        if ($invitation === null) {
            return redirect()->route('invitations')
                ->with('error', __('This invitation link is no longer valid.'));
        }

        // Only the invited address may accept (case-insensitive).
        if (strcasecmp($invitation->email, $user->email) !== 0) {
            return redirect()->route('invitations')
                ->with('error', __('This invitation was sent to :email. Please sign in as that user.', ['email' => $invitation->email]));
        }

        if ($tenantService->acceptInvitation($invitation, $user) === false) {
            return redirect()->route('invitations')
                ->with('error', __('You cannot accept this invitation, please contact support.'));
        }

        // Route to the panel matching the roles just assigned.
        $panel = $user->defaultSpPanel((int) $invitation->tenant_id);

        return redirect()->route("filament.{$panel}.pages.dashboard", ['tenant' => $invitation->tenant->uuid]);
    }
}
