<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Mail\StaffPick\SchedulerAlert;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Fans scheduler/staff alerts out to a tenant's admins across all three channels:
 * Filament database notification (bell), queued email, and Slack. Used by the offer
 * pipeline for assignment acceptance and queue exhaustion.
 */
class SchedulerNotificationService
{
    public function __construct(
        private TenantPermissionService $permissions,
        private SlackNotificationService $slack,
    ) {}

    public function notifyAssignmentAccepted(Assignment $assignment): void
    {
        $assignment->loadMissing(['intakeRequest', 'provider']);
        $intake = $assignment->intakeRequest;
        $tenant = Tenant::find($assignment->tenant_id);
        $reference = $intake?->reference_number ?? '—';
        $provider = trim("{$assignment->provider?->first_name} {$assignment->provider?->last_name}") ?: __('A provider');
        $url = $intake ? $this->intakeUrl($intake) : null;

        $heading = __('Assignment confirmed');
        $body = __(':provider accepted referral :reference.', ['provider' => $provider, 'reference' => $reference]);

        $this->toAdmins($tenant, $heading, $body, $url);

        $this->slack->notifyProviderAssigned($assignment);
    }

    /**
     * Alert tenant admins (Filament bell + Slack) that a provider credential is
     * expiring soon. Reads expires_at, so call only where pdo_sqlsrv is available.
     */
    public function credentialExpiring(ProviderCredential $credential): void
    {
        $credential->loadMissing(['provider', 'documentType']);
        $tenant = Tenant::find($credential->provider?->tenant_id);
        $admins = $this->admins($tenant);

        if ($admins->isNotEmpty()) {
            $provider = trim("{$credential->provider?->first_name} {$credential->provider?->last_name}") ?: __('A provider');
            $type = $credential->documentType?->name ?? __('Credential');
            $expiry = $credential->expires_at?->format('M j, Y') ?? __('soon');

            Notification::make()
                ->title(__('Credential expiring soon'))
                ->body(__(':type for :provider expires :expiry.', ['type' => $type, 'provider' => $provider, 'expiry' => $expiry]))
                ->warning()
                ->sendToDatabase($admins);
        }

        $this->slack->notifyCredentialExpiring($credential);
    }

    public function notifyNoClinicians(IntakeRequest $intake): void
    {
        $tenant = Tenant::find($intake->tenant_id);
        $reference = $intake->reference_number ?? '—';

        $heading = __('No clinicians available');
        $body = __('Referral :reference exhausted its offer queue with no clinician assigned. Re-trigger matching with an expanded radius from the case.', ['reference' => $reference]);

        $this->toAdmins($tenant, $heading, $body, $this->intakeUrl($intake));

        $this->slack->notifyNoClinicians($intake);
    }

    /**
     * Send a bell notification (with an optional deep link) and a queued email to
     * every admin of the tenant.
     */
    private function toAdmins(?Tenant $tenant, string $heading, string $body, ?string $url): void
    {
        $admins = $this->admins($tenant);

        if ($admins->isEmpty()) {
            return;
        }

        $notification = Notification::make()->title($heading)->body($body);

        if (filled($url)) {
            $notification->actions([
                NotificationAction::make('view')->label(__('View case'))->url($url)->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($admins);

        $admins
            ->filter(fn (User $user): bool => filled($user->email))
            ->each(fn (User $user) => Mail::to($user->email)->queue(new SchedulerAlert($heading, $body, $url)));
    }

    /**
     * Tenant admins, falling back to all tenant users when roles aren't resolvable.
     *
     * @return Collection<int, User>
     */
    private function admins(?Tenant $tenant): Collection
    {
        if (! $tenant instanceof Tenant) {
            return collect();
        }

        $users = $tenant->users()->get();

        try {
            $admins = $users->filter(fn (User $user): bool => in_array(
                TenancyPermissionConstants::ROLE_ADMIN,
                $this->permissions->getTenantUserRoles($tenant, $user),
                true,
            ));

            if ($admins->isNotEmpty()) {
                return $admins->values();
            }
        } catch (Throwable) {
            // Fall through to notifying all tenant users.
        }

        return $users->values();
    }

    private function intakeUrl(IntakeRequest $intake): ?string
    {
        try {
            $tenant = Tenant::find($intake->tenant_id);

            if ($tenant === null) {
                return null;
            }

            return IntakeRequestResource::getUrl('view', ['record' => $intake->getKey()], tenant: $tenant);
        } catch (Throwable) {
            return null;
        }
    }
}
