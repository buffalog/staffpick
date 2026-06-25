<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\StaffPick\ReferralSource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Purpose-built staff operations landing page. Replaces Filament's default dashboard
 * (and the AccountWidget) with an ops view: oldest-pending alert, stat cards, and
 * quick-action cards. All data is scoped to the current tenant.
 */
class Dashboard extends BaseDashboard
{
    /** Dispatch-queue statuses (per the dashboard spec). */
    public const PENDING = ['unmatched', 'match_sent', 'escalated'];

    public const ACTIVE = ['matched'];

    public const COMPLETED = ['completed', 'cancelled', 'on_hold'];

    protected string $view = 'filament.dashboard.pages.staff-dashboard';

    /** No header/footer widgets — the custom view renders everything inline. */
    public function getWidgets(): array
    {
        return [];
    }

    public function getHeaderWidgets(): array
    {
        return [];
    }

    private function tenantId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant->id : null;
    }

    /** @param array<int, string> $statuses */
    private function scoped(array $statuses): Builder
    {
        return IntakeRequest::query()
            ->where('tenant_id', $this->tenantId())
            ->whereIn('status', $statuses);
    }

    // ---- Section 1: oldest-pending banner ------------------------------------

    public function oldestPending(): ?IntakeRequest
    {
        return $this->scoped(self::PENDING)
            ->with(['subject', 'discipline', 'referralSource'])
            ->orderBy('created_at')
            ->first();
    }

    public function daysWaiting(?IntakeRequest $intake): int
    {
        return $intake?->created_at !== null
            ? (int) $intake->created_at->diffInDays(now())
            : 0;
    }

    public function findMatchesUrl(IntakeRequest $intake): string
    {
        return IntakeRequestResource::getUrl('view', ['record' => $intake]);
    }

    // ---- Section 2: stat cards (rendered via StaffDashboardStats widget) ------
    // counts are also used by the banner/board; exposed for the widget.

    public function pendingCount(): int
    {
        return $this->scoped(self::PENDING)->count();
    }

    public function activeCount(): int
    {
        return $this->scoped(self::ACTIVE)->count();
    }

    public function completedCount(): int
    {
        return $this->scoped(self::COMPLETED)->count();
    }

    // ---- Section 3: quick-action cards ---------------------------------------

    public function activeProviderCount(): int
    {
        return Provider::query()
            ->where('tenant_id', $this->tenantId())
            ->where('status', Provider::STATUS_ACTIVE)
            ->where('is_active', true)
            ->count();
    }

    /** Credentials needing attention (unverified/failed or expiring ≤30d) — tenant-wide. */
    public function credentialAlertCount(): int
    {
        return ProviderCredential::query()
            ->whereHas('provider', fn (Builder $q) => $q->where('tenant_id', $this->tenantId()))
            ->where(function (Builder $q): void {
                $q->whereIn('verification_status', [
                    ProviderCredential::VERIFICATION_UNVERIFIED,
                    ProviderCredential::VERIFICATION_FAILED,
                ])->orWhere(fn (Builder $sub) => $sub
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()]));
            })
            ->count();
    }

    /** @return Collection<int, Provider> */
    public function recentProviders(): Collection
    {
        return Provider::query()
            ->where('tenant_id', $this->tenantId())
            ->with(['discipline', 'tier'])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
    }

    public function addProviderUrl(): string
    {
        return ProviderResource::getUrl('create');
    }

    /** Public self-serve application link for this tenant (for the "copy link" action). */
    public function applicationLinkUrl(): string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant
            ? route('staffpick.application.show', ['tenantSlug' => $tenant->uuid])
            : '';
    }

    public function sourceCount(): int
    {
        return ReferralSource::query()->where('tenant_id', $this->tenantId())->count();
    }

    public function lastSourceAddedDaysAgo(): ?int
    {
        $last = ReferralSource::query()
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->first();

        return $last?->created_at !== null ? (int) $last->created_at->diffInDays(now()) : null;
    }

    /** @return Collection<int, ReferralSource> */
    public function recentSources(): Collection
    {
        return ReferralSource::query()
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
    }

    public function addSourceUrl(): string
    {
        return ReferralSourceResource::getUrl('create');
    }
}
