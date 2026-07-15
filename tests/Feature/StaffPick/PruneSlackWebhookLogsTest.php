<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\SlackWebhookLog;
use Tests\Feature\FeatureTest;

class PruneSlackWebhookLogsTest extends FeatureTest
{
    private function logAgedDays(int $days): SlackWebhookLog
    {
        $log = new SlackWebhookLog([
            'tenant_id' => $this->createTenant()->id,
            'event_type' => 'message',
            'signature_valid' => true,
            'payload' => '{"text":"referral for a patient"}',
        ]);
        // Set created_at before insert so Eloquent doesn't stamp "now" over it.
        $log->created_at = now()->subDays($days);
        $log->save();

        return $log;
    }

    public function test_it_deletes_logs_past_the_retention_window_and_keeps_recent_ones(): void
    {
        config(['staffpick.slack_webhook_log_retention_days' => 30]);

        $old = $this->logAgedDays(60);    // outside the 30-day window
        $recent = $this->logAgedDays(5);  // inside the window

        $this->artisan('staffpick:prune-slack-webhook-logs')->assertSuccessful();

        $this->assertDatabaseMissing('sp_slack_webhook_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('sp_slack_webhook_logs', ['id' => $recent->id]);
    }
}
