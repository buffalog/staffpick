<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit record for an inbound Slack webhook request. Not directly tenant-scoped
 * (tenant resolved from the URL token); see the inbound webhook controller.
 *
 * Deliberately NOT BelongsToTenant: the only reader today is cross-tenant super-admin
 * support, so a tenant global scope would add no security now and would complicate that
 * read. Revisit if a tenant-facing read is ever added, alongside H3b's ->crossTenant() opt-in.
 *
 * The payload holds the raw inbound message body (referral PHI for a real event), so it is
 * bounded at rest by staffpick:prune-slack-webhook-logs (see config staffpick.slack_webhook_log_retention_days).
 */
class SlackWebhookLog extends Model
{
    protected $table = 'sp_slack_webhook_logs';

    protected $fillable = [
        'tenant_id',
        'event_type',
        'signature_valid',
        'intake_request_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'intake_request_id' => 'integer',
            'signature_valid' => 'boolean',
        ];
    }
}
