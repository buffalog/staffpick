<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit record for an inbound Slack webhook request. Not directly tenant-scoped
 * (tenant resolved from the URL token); see the inbound webhook controller.
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
            'signature_valid' => 'boolean',
        ];
    }
}
