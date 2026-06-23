<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Concerns;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Services\StaffPick\AssignmentService;
use App\Services\StaffPick\OfferService;
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
     * Semi-auto: dispatch a single offer to one matched provider. Keeps the modal open
     * so staff can offer multiple providers; does not unmount the action.
     */
    public function dispatchOfferToProvider(int $intakeRequestId, int $providerId): void
    {
        $intakeRequest = IntakeRequest::find($intakeRequestId);
        $provider = Provider::find($providerId);

        if ($intakeRequest === null || $provider === null) {
            return;
        }

        abort_unless(SpRoleAccess::isAdminOrStaff(), 403);

        app(OfferService::class)->dispatchToProvider($intakeRequest, $provider);

        if (! in_array($providerId, $this->offeredProviderIds, true)) {
            $this->offeredProviderIds[] = $providerId;
        }

        Notification::make()
            ->title(__('Offer dispatched to :provider', ['provider' => trim("{$provider->first_name} {$provider->last_name}")]))
            ->success()
            ->send();
    }

    public function assignProvider(int $intakeRequestId, int $providerId): void
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

        app(AssignmentService::class)->assign($intakeRequest, $provider);

        Notification::make()
            ->title(__('Assigned :provider', ['provider' => trim("{$provider->first_name} {$provider->last_name}")]))
            ->success()
            ->send();

        $this->unmountAction();
    }
}
