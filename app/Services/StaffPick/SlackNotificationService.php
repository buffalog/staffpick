<?php

namespace App\Services\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Jobs\StaffPick\SendSlackNotification;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use Throwable;

/**
 * Builds and queues outbound Slack notifications (Block Kit) for StaffPick domain
 * events. Resolves the destination webhook per tenant (tenant override, else the
 * global SLACK_WEBHOOK_URL); when no webhook is configured the call is a no-op, so
 * Slack is opt-in and never breaks the originating flow. All sends are queued.
 */
class SlackNotificationService
{
    /**
     * Event (a): a new intake request was received. Patient first name only (HIPAA).
     */
    public function notifyIntakeReceived(IntakeRequest $intake): void
    {
        $intake->loadMissing(['subject', 'discipline', 'referralSource']);

        $reference = $intake->reference_number ?? '—';
        $patient = $intake->subject?->first_name ?? __('Unknown');
        $discipline = $intake->discipline?->name ?? __('Unspecified');
        $source = $intake->referralSource?->name ?? __('Unknown');

        $this->dispatch($intake->tenant_id, __('New intake request received: :reference', ['reference' => $reference]), [
            $this->header(__('New intake request')),
            $this->fields([
                __('Reference') => $reference,
                __('Patient') => $patient,
                __('Discipline') => $discipline,
                __('Referral source') => $source,
            ]),
            ...$this->linkButton(__('Open in dashboard'), $this->intakeUrl($intake)),
        ]);
    }

    /**
     * Event (b): a provider was assigned/offered to an intake request.
     */
    public function notifyProviderAssigned(Assignment $assignment): void
    {
        $assignment->loadMissing(['provider', 'intakeRequest.discipline']);

        $provider = $this->providerName($assignment->provider);
        $reference = $assignment->intakeRequest?->reference_number ?? '—';
        $discipline = $assignment->intakeRequest?->discipline?->name ?? __('Unspecified');

        $this->dispatch($assignment->tenant_id, __('Provider :provider assigned to :reference', [
            'provider' => $provider,
            'reference' => $reference,
        ]), [
            $this->header(__('Provider assigned')),
            $this->fields([
                __('Provider') => $provider,
                __('Reference') => $reference,
                __('Discipline') => $discipline,
            ]),
        ]);
    }

    /**
     * The offer queue for an intake was exhausted (all offers declined/expired) with
     * no clinician assigned.
     */
    public function notifyNoClinicians(IntakeRequest $intake): void
    {
        $intake->loadMissing(['discipline', 'referralSource']);

        $reference = $intake->reference_number ?? '—';
        $discipline = $intake->discipline?->name ?? __('Unspecified');
        $referralSource = $intake->referralSource?->name ?? __('Unspecified');

        $this->dispatch($intake->tenant_id, __('No clinicians available for :reference', ['reference' => $reference]), [
            $this->header(__('No clinicians available')),
            $this->fields([
                __('Reference') => $reference,
                __('Discipline') => $discipline,
                __('Referral source') => $referralSource,
            ]),
            ...$this->linkButton(__('Open in dashboard'), $this->intakeUrl($intake)),
        ]);
    }

    /**
     * Event (c): a clinician submitted their profile for review.
     */
    public function notifyProviderProfileSubmitted(Provider $provider): void
    {
        $provider->loadMissing('discipline');

        $name = $this->providerName($provider);
        $discipline = $provider->discipline?->name ?? __('Unspecified');

        $this->dispatch($provider->tenant_id, __(':provider submitted a profile for review', ['provider' => $name]), [
            $this->header(__('Provider profile submitted for review')),
            $this->fields([
                __('Provider') => $name,
                __('Discipline') => $discipline,
            ]),
        ]);
    }

    /**
     * Event (d): a credential is expiring soon.
     */
    public function notifyCredentialExpiring(ProviderCredential $credential): void
    {
        $credential->loadMissing(['provider', 'documentType']);

        $provider = $this->providerName($credential->provider);
        $type = $credential->documentType?->name ?? __('Credential');
        $expiry = $credential->expires_at?->format('M j, Y') ?? __('Unknown');

        $this->dispatch($credential->provider?->tenant_id, __('Credential expiring: :type for :provider', [
            'type' => $type,
            'provider' => $provider,
        ]), [
            $this->header(__('Credential expiring soon')),
            $this->fields([
                __('Provider') => $provider,
                __('Credential') => $type,
                __('Expires') => $expiry,
            ]),
        ]);
    }

    /**
     * Post a plain text confirmation back to a tenant's Slack (used by the inbound
     * webhook to acknowledge a created draft intake).
     */
    public function notifyText(?int $tenantId, string $text): void
    {
        $this->dispatch($tenantId, $text, [
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]],
        ]);
    }

    /**
     * Queue a Block Kit message to the tenant's resolved webhook, if any.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function dispatch(?int $tenantId, string $fallbackText, array $blocks): void
    {
        $webhookUrl = $this->resolveWebhookUrl($tenantId);

        if (blank($webhookUrl)) {
            return;
        }

        SendSlackNotification::dispatch($webhookUrl, [
            'text' => $fallbackText,
            'blocks' => array_values($blocks),
        ]);
    }

    private function resolveWebhookUrl(?int $tenantId): ?string
    {
        $config = $tenantId
            ? TenantConfig::query()->where('tenant_id', $tenantId)->first()
            : null;

        return $config?->slackWebhookUrl() ?? config('services.slack.webhook_url');
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $text): array
    {
        return ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $text]];
    }

    /**
     * @param  array<string, string>  $pairs
     * @return array<string, mixed>
     */
    private function fields(array $pairs): array
    {
        $fields = [];

        foreach ($pairs as $label => $value) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*{$label}:*\n".$value];
        }

        return ['type' => 'section', 'fields' => $fields];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linkButton(string $label, ?string $url): array
    {
        if (blank($url)) {
            return [];
        }

        return [[
            'type' => 'actions',
            'elements' => [[
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => $label],
                'url' => $url,
            ]],
        ]];
    }

    private function providerName(?Provider $provider): string
    {
        if ($provider === null) {
            return __('Unknown');
        }

        return trim("{$provider->first_name} {$provider->last_name}") ?: __('Unknown');
    }

    private function intakeUrl(IntakeRequest $intake): ?string
    {
        try {
            $tenant = Tenant::find($intake->tenant_id);

            if ($tenant === null) {
                return null;
            }

            // Force the dashboard panel: Slack alerts are dispatched from queued jobs /
            // the CheckOfferExpiry cron with no current panel, where getUrl would default
            // to the admin panel and throw RouteNotFoundException (dropping the link).
            return IntakeRequestResource::getUrl('view', ['record' => $intake->getKey()], panel: 'dashboard', tenant: $tenant);
        } catch (Throwable) {
            return null;
        }
    }
}
