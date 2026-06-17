<?php

namespace App\Console\Commands;

use App\Models\StaffPick\ProviderCredential;
use App\Services\StaffPick\SchedulerNotificationService;
use Illuminate\Console\Command;

/**
 * Daily check for active providers' credentials expiring within the lookahead window,
 * alerting tenant admins via Slack + Filament bell. Scheduled in routes/console.php.
 * The command only queries (it never reads the date cast itself); the notifier formats
 * the expiry, so this stays safe to drive from anywhere.
 */
class CheckCredentialExpiry extends Command
{
    protected $signature = 'staffpick:check-credential-expiry {--days=30 : Days of lookahead}';

    protected $description = 'Alert tenant admins about provider credentials expiring soon.';

    public function handle(SchedulerNotificationService $scheduler): int
    {
        $days = (int) $this->option('days');

        $credentials = ProviderCredential::query()
            ->whereNotNull('expires_at')
            ->where('status', '!=', 'expired')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->get();

        $credentials->each(fn (ProviderCredential $credential) => $scheduler->credentialExpiring($credential));

        $this->info("Notified tenant admins for {$credentials->count()} expiring credential(s).");

        return self::SUCCESS;
    }
}
