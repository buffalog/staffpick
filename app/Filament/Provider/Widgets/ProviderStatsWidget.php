<?php

namespace App\Filament\Provider\Widgets;

use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Provider-home stat cards: tier, active case count, and credential alerts. Inline
 * header widget on ProviderHome (returned from getHeaderWidgets). Self-resolves the
 * signed-in clinician's provider, like MyCasesCalendar, so it needs no data passed in.
 */
class ProviderStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return [];
        }

        $tierName = $provider->tier?->name;
        $alertCount = $this->credentialAlertCount($provider);

        return [
            Stat::make(__('Tier'), $tierName ?? __('No tier assigned'))
                ->description(__('Provider Tier'))
                ->descriptionIcon('heroicon-o-star')
                ->color(match ($tierName) {
                    'Gold' => 'warning',
                    'Silver' => 'gray',
                    'Platinum' => 'success',
                    default => 'gray',
                }),
            Stat::make(__('Active Cases'), $this->activeCaseCount($provider))
                ->description(__('Active Cases'))
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('primary'),
            Stat::make(__('Credential Alerts'), $alertCount)
                ->description(__('Expiring or Expired'))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($alertCount > 0 ? 'danger' : 'success'),
        ];
    }

    /** The Provider record linked to the current user for the active tenant, if any. */
    private function provider(): ?Provider
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return null;
        }

        return $user->providerForTenant($tenant->id);
    }

    /** Assigned cases that aren't completed or cancelled. */
    private function activeCaseCount(Provider $provider): int
    {
        return IntakeRequest::query()
            ->where('tenant_id', $provider->tenant_id)
            ->whereHas('assignments', fn (Builder $sub): Builder => $sub
                ->where('provider_id', $provider->id)
                ->where('status', '!=', Assignment::STATUS_CANCELLED))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
    }

    /**
     * Credentials that are expired or within their type's warning window. expires_at is
     * a 'date'-cast column, so it's read via getRawOriginal() (raw string) + Carbon to
     * stay off the cast (dblib-safe).
     */
    private function credentialAlertCount(Provider $provider): int
    {
        $today = now()->startOfDay();

        return ProviderCredential::query()
            ->where('provider_id', $provider->id)
            ->with('documentType')
            ->get()
            ->filter(function (ProviderCredential $credential) use ($today): bool {
                if ($credential->status === 'expired') {
                    return true;
                }

                $raw = $credential->getRawOriginal('expires_at');

                if (blank($raw)) {
                    return false;
                }

                $warningDays = (int) ($credential->documentType?->expiry_warning_days ?? 0);

                return Carbon::parse($raw)->startOfDay()->lte($today->copy()->addDays($warningDays));
            })
            ->count();
    }
}
