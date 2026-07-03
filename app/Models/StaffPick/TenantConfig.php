<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_tenant_configs';

    protected $fillable = [
        'tenant_id',
        'default_radius_miles',
        'feathering_miles',
        'offer_window_seconds',
        'offer_delay_seconds',
        'auto_dispatch',
        'intake_person_is_assigner',
        'default_provider_is_contractor',
        'billing_terms_days',
        'week_ending_day',
        'notify_push',
        'notify_email',
        'notify_sms',
        'referral_portal_enabled',
        'show_booked_option_in_app',
        'entity_label_provider',
        'entity_label_subject',
        'entity_label_intake_request',
        'entity_label_discipline',
        'rating_internal_min',
        'rating_patient_min',
        'rating_promotion_threshold',
        'rating_demotion_threshold',
        'rating_min_survey_count',
        'rating_review_period',
        'slack_webhook_url',
        'slack_signing_secret',
        'slack_inbound_token',
        'slack_intake_keyword',
        'sso_provider',
        'sso_client_id',
        'sso_client_secret',
        'sso_domain',
        'sso_enabled',
        'sso_required',
    ];

    /**
     * Generate and persist a unique inbound-webhook token if one isn't set, then
     * return it. The token namespaces this tenant's public Slack inbound URL.
     */
    public function ensureSlackInboundToken(): string
    {
        if (blank($this->slack_inbound_token)) {
            $this->forceFill(['slack_inbound_token' => bin2hex(random_bytes(20))])->save();
        }

        return $this->slack_inbound_token;
    }

    /**
     * The keyword that, when present in an inbound Slack message, triggers draft
     * intake creation. Falls back to a sensible default.
     */
    public function slackIntakeKeyword(): string
    {
        return filled($this->slack_intake_keyword) ? $this->slack_intake_keyword : 'new referral';
    }

    /** Per-tenant signing secret, falling back to the global app-level secret. */
    public function slackSigningSecret(): ?string
    {
        return $this->safeEncrypted('slack_signing_secret') ?: config('services.slack.signing_secret');
    }

    /**
     * Read an `encrypted`-cast attribute without letting an undecryptable value throw
     * into a request path. A rotated APP_KEY — or legacy plaintext not yet migrated —
     * would otherwise 500 every caller (this is what broke the inbound Slack webhook).
     * Returns null on failure so the caller falls back to its config-level default.
     */
    private function safeEncrypted(string $key): ?string
    {
        try {
            return $this->getAttribute($key);
        } catch (DecryptException) {
            return null;
        }
    }

    /** Resolved outbound webhook URL: tenant override, else the global default. */
    public function slackWebhookUrl(): ?string
    {
        return $this->slack_webhook_url ?: config('services.slack.webhook_url');
    }

    /** Seconds an offer stays open before expiring and advancing the queue. */
    public function offerDelaySeconds(): int
    {
        return (int) ($this->offer_delay_seconds ?: 300);
    }

    protected function casts(): array
    {
        return [
            'default_radius_miles' => 'integer',
            'feathering_miles' => 'integer',
            'offer_window_seconds' => 'integer',
            'offer_delay_seconds' => 'integer',
            'auto_dispatch' => 'boolean',
            'intake_person_is_assigner' => 'boolean',
            'default_provider_is_contractor' => 'boolean',
            'billing_terms_days' => 'integer',
            'notify_push' => 'boolean',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'referral_portal_enabled' => 'boolean',
            'show_booked_option_in_app' => 'boolean',
            'rating_internal_min' => 'decimal:2',
            'rating_patient_min' => 'decimal:2',
            'rating_promotion_threshold' => 'decimal:2',
            'rating_demotion_threshold' => 'decimal:2',
            'rating_min_survey_count' => 'integer',
            'sso_enabled' => 'boolean',
            'sso_required' => 'boolean',
            // Secrets are encrypted at rest; the cast transparently encrypts on
            // write and decrypts on read. NOTE: any pre-existing plaintext values
            // must be re-saved (or migrated) once, or reads will throw on decrypt.
            'sso_client_secret' => 'encrypted',
            'slack_signing_secret' => 'encrypted',
        ];
    }

    public function ssoEnabled(): bool
    {
        return (bool) $this->sso_enabled && filled($this->sso_provider);
    }

    public function ssoRequired(): bool
    {
        return $this->ssoEnabled() && (bool) $this->sso_required;
    }

    /**
     * Resolve a tenant-configurable singular entity label for the current Filament
     * tenant, falling back to the given default when there is no tenant context, no
     * config row, or the stored value is blank.
     *
     * Reads through the cached Tenant->config relationship, so repeated calls within
     * a request (e.g. one per resource in the sidebar) hit the database at most once.
     */
    public static function entityLabel(string $entity, string $default): string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return $default;
        }

        $value = $tenant->config?->{'entity_label_'.$entity};

        return filled($value) ? $value : $default;
    }
}
