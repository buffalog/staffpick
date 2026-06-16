<?php

namespace App\Jobs\StaffPick;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Posts a pre-built Slack Block Kit payload to an Incoming Webhook URL. Queued so a
 * slow or failing Slack endpoint never blocks the originating request.
 */
class SendSlackNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $webhookUrl,
        public array $payload,
    ) {}

    public function handle(): void
    {
        Http::asJson()
            ->timeout(10)
            ->post($this->webhookUrl, $this->payload);
    }
}
