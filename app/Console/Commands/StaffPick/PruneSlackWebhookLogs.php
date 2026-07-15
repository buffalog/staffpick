<?php

namespace App\Console\Commands\StaffPick;

use App\Models\StaffPick\SlackWebhookLog;
use Illuminate\Console\Command;

/**
 * Deletes inbound Slack webhook audit records past their retention window. The payload holds
 * the raw inbound message body — for a real event, referral PHI that became a draft intake —
 * so bounding how long it sits at rest limits PHI exposure. Scheduled daily.
 *
 * The query is intentionally cross-tenant: SlackWebhookLog carries no BelongsToTenant, and
 * this is an infrastructure prune, not tenant data access.
 */
class PruneSlackWebhookLogs extends Command
{
    protected $signature = 'staffpick:prune-slack-webhook-logs';

    protected $description = 'Delete inbound Slack webhook audit logs past the configured retention window.';

    public function handle(): int
    {
        $days = (int) config('staffpick.slack_webhook_log_retention_days', 30);

        $deleted = SlackWebhookLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} Slack webhook log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
