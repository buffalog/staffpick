<?php

namespace App\Console\Commands;

use App\Models\StaffPick\ProviderCredential;
use App\Services\StaffPick\SlackNotificationService;
use Illuminate\Console\Command;

/**
 * Queues a Slack notification for each provider credential expiring within the next
 * 30 days. Scheduled daily; see routes/console.php.
 */
class NotifyExpiringCredentials extends Command
{
    protected $signature = 'staffpick:notify-expiring-credentials {--days=30 : Days of lookahead}';

    protected $description = 'Notify Slack about provider credentials expiring soon.';

    public function handle(SlackNotificationService $slack): int
    {
        $days = (int) $this->option('days');

        $credentials = ProviderCredential::query()
            ->with(['provider', 'documentType'])
            ->whereNotNull('expires_at')
            ->where('status', '!=', 'expired')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->get();

        foreach ($credentials as $credential) {
            $slack->notifyCredentialExpiring($credential);
        }

        $this->info("Queued {$credentials->count()} expiring-credential notification(s).");

        return self::SUCCESS;
    }
}
