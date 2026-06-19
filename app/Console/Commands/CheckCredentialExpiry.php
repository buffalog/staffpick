<?php

namespace App\Console\Commands;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\ProviderCredential;
use App\Services\StaffPick\CredentialComplianceService;
use App\Services\StaffPick\SchedulerNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daily credential compliance sweep across all tenants. Scheduled in routes/console.php.
 *
 *  1. Auto-deactivate providers holding a past-expiry credential whose type is marked
 *     deactivate_on_expiry (delegated to {@see CredentialComplianceService}).
 *  2. Warn tenant admins about credentials approaching expiry, using each credential
 *     type's own warning_days window (expiry_warning_days) rather than one flat value.
 *
 * Selection stays in SQL (date-string comparisons); the only date cast read happens in
 * the notifier/service, kept safe for the local FreeTDS driver.
 */
class CheckCredentialExpiry extends Command
{
    protected $signature = 'staffpick:check-credential-expiry';

    protected $description = 'Auto-deactivate providers with lapsed credentials and warn admins about ones expiring soon.';

    public function handle(SchedulerNotificationService $scheduler, CredentialComplianceService $compliance): int
    {
        $deactivated = $compliance->deactivateExpired();

        $today = now()->startOfDay();
        $warned = 0;

        // Each distinct warning window drives its own query so the lookahead stays in
        // SQL (no per-row date-cast read). Types with warning_days = 0 opt out entirely.
        $windows = CredentialDocumentType::query()
            ->where('has_expiry', true)
            ->where('expiry_warning_days', '>', 0)
            ->distinct()
            ->pluck('expiry_warning_days');

        foreach ($windows as $days) {
            $days = (int) $days;

            $credentials = ProviderCredential::query()
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()])
                ->where('status', '!=', 'expired')
                ->whereHas('documentType', fn (Builder $query) => $query
                    ->where('has_expiry', true)
                    ->where('expiry_warning_days', $days))
                ->whereHas('provider', fn (Builder $query) => $query->where('is_active', true))
                ->get();

            $credentials->each(fn (ProviderCredential $credential) => $scheduler->credentialExpiring($credential));

            $warned += $credentials->count();
        }

        $this->info("Auto-deactivated {$deactivated} provider(s); warned admins for {$warned} expiring credential(s).");

        return self::SUCCESS;
    }
}
