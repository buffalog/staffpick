<?php

namespace App\Services\StaffPick;

use App\Mail\StaffPick\SchedulerAlert;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Credential-driven provider activation. A credential type can be marked
 * deactivate_on_expiry: when such a credential lapses, the provider is auto-
 * deactivated; once every lapsed credential is renewed (and verified), the provider
 * is auto-reactivated. Notifications go to tenant admins (bell + email + Slack) and
 * to the provider on their preferred channel.
 *
 * Selection stays in SQL (date-string comparisons) and the only place a date cast is
 * read is {@see formatExpiry()}, which is guarded — the local FreeTDS driver throws
 * on populated date columns, Railway's pdo_sqlsrv does not.
 */
class CredentialComplianceService
{
    public const REASON_PREFIX = 'Credential expired:';

    public function __construct(
        private SchedulerNotificationService $scheduler,
        private SmsService $sms,
    ) {}

    /**
     * Deactivate every active provider holding a past-expiry credential whose type is
     * marked deactivate_on_expiry. Returns the number of providers deactivated.
     */
    public function deactivateExpired(): int
    {
        $count = 0;

        $this->expiredDeactivatingCredentials()
            ->groupBy('provider_id')
            ->each(function (Collection $credentials) use (&$count): void {
                $credential = $credentials->first();
                $provider = $credential->provider;

                if (! $provider instanceof Provider || ! $provider->is_active) {
                    return;
                }

                $type = $credential->documentType?->name ?? __('A credential');
                $expiry = $this->formatExpiry($credential);

                $provider->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                    'deactivation_reason' => self::REASON_PREFIX." {$type} expired on {$expiry}",
                ]);

                $this->scheduler->providerAutoDeactivated($provider, $type, $expiry);
                $this->notifyProvider($provider, $type);

                $count++;
            });

        return $count;
    }

    /**
     * Reactivate a provider that was auto-deactivated for credential expiry, but only
     * once no deactivate_on_expiry credential remains past its expiry. Called after a
     * credential is marked verified. Returns whether the provider was reactivated.
     */
    public function reactivateIfEligible(Provider $provider): bool
    {
        $provider->refresh();

        if ($provider->is_active) {
            return false;
        }

        // Only auto-reactivate providers we auto-deactivated for credential expiry —
        // never ones an admin deactivated manually or that were rejected.
        if (! str_starts_with((string) $provider->deactivation_reason, self::REASON_PREFIX)) {
            return false;
        }

        $stillExpired = ProviderCredential::query()
            ->where('provider_id', $provider->getKey())
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->startOfDay()->toDateString())
            ->whereHas('documentType', fn (Builder $query) => $query->where('deactivate_on_expiry', true))
            ->exists();

        if ($stillExpired) {
            return false;
        }

        $provider->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ]);

        $this->scheduler->providerReactivated($provider);

        return true;
    }

    /**
     * @return Collection<int, ProviderCredential>
     */
    private function expiredDeactivatingCredentials(): Collection
    {
        return ProviderCredential::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->startOfDay()->toDateString())
            ->whereHas('documentType', fn (Builder $query) => $query->where('deactivate_on_expiry', true))
            ->whereHas('provider', fn (Builder $query) => $query->where('is_active', true))
            ->with(['provider', 'documentType'])
            ->get();
    }

    /**
     * Tell the provider, on their preferred channel, that their profile is deactivated.
     */
    private function notifyProvider(Provider $provider, string $credentialType): void
    {
        $message = __('Your :type has expired. Your profile has been deactivated until you upload a renewed credential.', [
            'type' => $credentialType,
        ]);

        if ($provider->user !== null) {
            try {
                Notification::make()
                    ->title(__('Profile deactivated'))
                    ->body($message)
                    ->danger()
                    ->sendToDatabase($provider->user);
            } catch (Throwable $e) {
                report($e);
            }
        }

        try {
            match ($provider->preferred_contact_channel) {
                Provider::CHANNEL_SMS => filled($provider->phone)
                    ? $this->sms->send($provider->phone, $message)
                    : null,
                Provider::CHANNEL_PORTAL => null, // in-app bell only
                default => filled($provider->email)
                    ? Mail::to($provider->email)->queue(new SchedulerAlert(__('Profile deactivated'), $message))
                    : null,
            };
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Format a credential's expiry. Reading the date cast throws on the local FreeTDS
     * driver (Railway's pdo_sqlsrv is fine), so fall back to the raw stored value.
     */
    private function formatExpiry(ProviderCredential $credential): string
    {
        try {
            return $credential->expires_at?->format('M j, Y') ?? '';
        } catch (Throwable) {
            return (string) $credential->getRawOriginal('expires_at');
        }
    }
}
