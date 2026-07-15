<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Concerns;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Services\StaffPick\AssignmentService;
use App\Services\StaffPick\MatchDispatchService;
use Filament\Notifications\Notification;

/**
 * Shared Livewire actions for the "Find Matches" modal: assign a matched provider
 * directly, or dispatch a single offer to them (semi-auto flow). Included by the list
 * and view pages so the modal's per-row buttons resolve to a method on whichever page
 * hosts the modal.
 */
trait AssignsMatchedProviders
{
    /**
     * Provider IDs dispatched an offer during this modal session — drives the inline
     * "Offer Sent ✓" state. Unioned with live DB offers in the view.
     *
     * @var array<int, int>
     */
    public array $offeredProviderIds = [];

    /**
     * Set when a manual offer targets a provider who already has a live offer on
     * another case. The modal renders a confirm-and-proceed banner off this; null =
     * nothing awaiting confirmation.
     *
     * @var array{intakeRequestId:int, providerId:int, providerName:string, conflictReference:string}|null
     */
    public ?array $offerConfirmation = null;

    /**
     * Twin of {@see $offerConfirmation} for the Assign button: set when a direct assign
     * would commit a provider who still has a live offer out on another case.
     *
     * @var array{intakeRequestId:int, providerId:int, providerName:string, conflictReference:string}|null
     */
    public ?array $assignConfirmation = null;

    /**
     * Semi-auto: dispatch a single offer to one matched provider. Keeps the modal open
     * so staff can offer multiple providers; does not unmount the action.
     *
     * The auto-cascade treats a provider with a live offer on another case as "busy"
     * (dispatch()'s busyProviderIds) and skips them; the manual path deliberately can
     * override that — but not silently. A conflict surfaces a confirm-and-proceed prompt;
     * only $confirmed === true actually sends, and then unguarded, staff override wins.
     */
    public function dispatchOfferToProvider(int $intakeRequestId, int $providerId, bool $confirmed = false): void
    {
        $intakeRequest = IntakeRequest::find($intakeRequestId);
        $provider = Provider::find($providerId);

        if ($intakeRequest === null || $provider === null) {
            return;
        }

        abort_unless(SpRoleAccess::isAdminOrStaff(), 403);

        $conflict = $this->providerOfferConflict($provider, $intakeRequest);

        if ($conflict !== null && ! $confirmed) {
            $this->offerConfirmation = [
                'intakeRequestId' => (int) $intakeRequest->id,
                'providerId' => (int) $provider->id,
                'providerName' => trim("{$provider->first_name} {$provider->last_name}"),
                'conflictReference' => $conflict->intakeRequest?->reference_number ?: "#{$conflict->intake_request_id}",
            ];

            return;
        }

        $this->offerConfirmation = null;

        app(MatchDispatchService::class)->offerTo($intakeRequest, $provider);

        if (! in_array($providerId, $this->offeredProviderIds, true)) {
            $this->offeredProviderIds[] = $providerId;
        }

        Notification::make()
            ->title(__('Offer dispatched to :provider', ['provider' => trim("{$provider->first_name} {$provider->last_name}")]))
            ->success()
            ->send();
    }

    /** Dismiss a pending confirm-and-proceed prompt without sending. */
    public function cancelOfferConfirmation(): void
    {
        $this->offerConfirmation = null;
    }

    /**
     * A live (pending) offer this provider holds on ANOTHER case — the same "busy" signal
     * the auto-cascade uses (dispatch()'s busyProviderIds: pending + offered_at set + a
     * different case). Null when offering here won't over-commit them.
     */
    private function providerOfferConflict(Provider $provider, IntakeRequest $case): ?AssignmentOffer
    {
        return AssignmentOffer::query()
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->whereNotNull('offered_at')
            ->where('provider_id', $provider->id)
            ->where('intake_request_id', '!=', $case->id)
            ->with('intakeRequest')
            ->first();
    }

    public function assignProvider(int $intakeRequestId, int $providerId, bool $confirmed = false): void
    {
        // Both lookups run through the BelongsToTenant scope, so a record from
        // another tenant simply resolves to null.
        $intakeRequest = IntakeRequest::find($intakeRequestId);
        $provider = Provider::find($providerId);

        if ($intakeRequest === null || $provider === null) {
            return;
        }

        // Defense in depth: the hosting resource is already admin-gated, but this
        // is a directly-invokable Livewire RPC, so authorize the mutation here too.
        abort_unless(SpRoleAccess::isAdminOrStaff(), 403);

        // Same over-commitment guard as the offer path: assigning here commits the provider
        // while a live offer of theirs is still open on another case. Confirm-and-proceed.
        $conflict = $this->providerOfferConflict($provider, $intakeRequest);

        if ($conflict !== null && ! $confirmed) {
            $this->assignConfirmation = [
                'intakeRequestId' => (int) $intakeRequest->id,
                'providerId' => (int) $provider->id,
                'providerName' => trim("{$provider->first_name} {$provider->last_name}"),
                'conflictReference' => $conflict->intakeRequest?->reference_number ?: "#{$conflict->intake_request_id}",
            ];

            return; // keep the modal open; do NOT unmount
        }

        $this->assignConfirmation = null;

        app(AssignmentService::class)->assign($intakeRequest, $provider);

        Notification::make()
            ->title(__('Assigned :provider', ['provider' => trim("{$provider->first_name} {$provider->last_name}")]))
            ->success()
            ->send();

        $this->unmountAction();
    }

    /** Dismiss a pending assign confirm-and-proceed prompt without assigning. */
    public function cancelAssignConfirmation(): void
    {
        $this->assignConfirmation = null;
    }
}
