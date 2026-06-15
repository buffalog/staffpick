<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests\Concerns;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Services\StaffPick\AssignmentService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * Shared Livewire action for the "Find Matches" modal: assigns a matched provider
 * to an intake request. Included by the list and view pages so the modal's per-row
 * Assign buttons resolve to a method on whichever page hosts the modal.
 */
trait AssignsMatchedProviders
{
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
        abort_unless(Gate::allows('update', $intakeRequest), 403);

        app(AssignmentService::class)->assign($intakeRequest, $provider);

        Notification::make()
            ->title(__('Assigned :provider', ['provider' => trim("{$provider->first_name} {$provider->last_name}")]))
            ->success()
            ->send();

        $this->unmountAction();
    }
}
