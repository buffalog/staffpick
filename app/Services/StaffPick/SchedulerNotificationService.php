<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Mail\StaffPick\SchedulerAlert;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
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

    /**
     * Tell tenant admins a provider was auto-deactivated for an expired credential.
     * The expiry date is pre-formatted by the caller so this never reads the date cast
     * (which throws on the local FreeTDS driver).
     */
    public function providerAutoDeactivated(Provider $provider, string $credentialType, string $expiry): void
    {
        $tenant = Tenant::find($provider->tenant_id);
        $name = trim("{$provider->first_name} {$provider->last_name}") ?: __('A provider');

        $this->toAdmins(
            $tenant,
            __('Provider auto-deactivated'),
            __(':provider has been automatically deactivated — :type expired on :date. Review and reactivate once renewed.', [
                'provider' => $name,
                'type' => $credentialType,
                'date' => $expiry,
            ]),
            $this->providerUrl($provider),
        );

        $this->slack->notifyProviderDeactivated($provider, $credentialType, $expiry);
    }

    /**
     * Tell tenant admins a provider was auto-reactivated after renewing credentials.
     */
    public function providerReactivated(Provider $provider): void
    {
        $tenant = Tenant::find($provider->tenant_id);
        $name = trim("{$provider->first_name} {$provider->last_name}") ?: __('A provider');

        $this->toAdmins(
            $tenant,
            __('Provider reactivated'),
            __(':provider has been reactivated — all expiring credentials are valid again.', ['provider' => $name]),
            $this->providerUrl($provider),
        );

        $this->slack->notifyProviderReactivated($provider);
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
            ->each(function (User $user) use ($heading, $body, $url): void {
                try {
                    Mail::to($user->email)->queue(new SchedulerAlert($heading, $body, $url));
                } catch (Throwable $e) {
                    // Email is a best-effort side channel. With a sync queue a failing
                    // mailer (e.g. an unauthenticated SMTP) throws inline here — which
                    // previously aborted the method before the Slack alert on the next
                    // line. Swallow it so the bell and Slack channels are independent.
                    report($e);
                }
            });
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

            // Force the dashboard panel: this often runs outside a panel request
            // (the CheckOfferExpiry cron / queued jobs), where getUrl would otherwise
            // default to the admin panel and throw RouteNotFoundException.
            return IntakeRequestResource::getUrl('view', ['record' => $intake->getKey()], panel: 'dashboard', tenant: $tenant);
        } catch (Throwable) {
            return null;
        }
    }

    private function providerUrl(Provider $provider): ?string
    {
        try {
            $tenant = Tenant::find($provider->tenant_id);

            if ($tenant === null) {
                return null;
            }

            return ProviderResource::getUrl('view', ['record' => $provider->getKey()], panel: 'dashboard', tenant: $tenant);
        } catch (Throwable) {
            return null;
        }
    }
}
