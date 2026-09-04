<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Concerns\LogsRecordList;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Resources\ReferralSources\ReferralSourceResource;
use App\Filament\Dashboard\Support\SpRoleAccess;
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
    use LogsRecordList;

    /** Dispatch-queue statuses (per the dashboard spec). */
    public const PENDING = ['unmatched', 'match_sent', 'escalated'];

    public const ACTIVE = ['matched'];

    public const COMPLETED = ['completed', 'cancelled', 'on_hold'];

    protected string $view = 'filament.dashboard.pages.staff-dashboard';

    /**
     * Staff only. Everything on this page is tenant-wide — the banner names the oldest
     * pending case's subject, the roster names every provider — so a provider or referrer
     * seeing it is a minimum-necessary breach, not a cosmetic one. Every other page in this
     * panel already gates this way; this one is the panel's landing route, so it was missed.
     */
    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    /**
     * Replaces the CanAuthorizeAccess trait's flat abort(403) so a non-staff user who lands
     * here (bookmark, tenant switcher, stale link) is sent to the portal they actually belong
     * in. Anyone with nowhere better to go still 403s, and the trait's untouched
     * hydrateCanAuthorizeAccess() keeps 403ing on every later Livewire request.
     */
    public function mountCanAuthorizeAccess(): void
    {
        if (static::canAccess()) {
            return;
        }

        $home = $this->portalHomeUrl();

        abort_if($home === null, 403);

        $this->redirect($home);
    }

    /**
     * Where a non-staff member of this tenant belongs: the provider roster for sp_hr (which
     * has no portal of its own but works out of this panel), their own portal for a provider
     * or referrer, or the role picker if they hold no SP role yet. Precedence mirrors
     * {@see User::defaultSpPanel()}, which routes people here in the first place. Null only
     * when there is no resolved tenant or user.
     */
    private function portalHomeUrl(): ?string
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return null;
        }

        $roles = $user->spRolesForTenant($tenant->id);

        if ($roles === []) {
            return RoleSelection::getUrl();
        }

        // sp_hr: staff-side but not isAdminOrStaff, so this page is closed to them. The
        // provider roster is the one staff surface they do hold (SpRoleAccess::canEditProviders).
        if (SpRoleAccess::canEditProviders()) {
            return ProviderResource::getUrl('index');
        }

        foreach ([
            'provider' => TenancyPermissionConstants::ROLE_SP_PROVIDER,
            'referrer' => TenancyPermissionConstants::ROLE_SP_REFERRER,
        ] as $panel => $role) {
            if (in_array($role, $roles, true)) {
                return route("filament.{$panel}.pages.dashboard", ['tenant' => $tenant->uuid]);
            }
        }

        return null;
    }

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
        $oldest = $this->scoped(self::PENDING)
            ->with(['subject', 'discipline', 'referralSource'])
            ->orderBy('created_at')
            ->first();

        // staff-dashboard.blade.php:16 prints this patient's surname in the alert banner. One
        // patient is a smaller disclosure than a full list, not a different kind of one.
        if ($oldest !== null) {
            $this->logListedRecords([$oldest], ['scope' => 'oldest_pending_banner']);
        }

        return $oldest;
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
