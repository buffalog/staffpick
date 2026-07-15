<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack webhook log retention
    |--------------------------------------------------------------------------
    |
    | How many days inbound Slack webhook audit records (sp_slack_webhook_logs)
    | are kept before the daily prune deletes them. Their payload holds the raw
    | inbound Slack message body, which for a real event is referral PHI — so
    | this is a PHI-retention policy value, deliberately tunable. Pruned by
    | staffpick:prune-slack-webhook-logs.
    |
    */

    'slack_webhook_log_retention_days' => (int) env('SLACK_WEBHOOK_LOG_RETENTION_DAYS', 30),

];
