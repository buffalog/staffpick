<?php

namespace App\Filament\Provider\Pages;

use App\Filament\Dashboard\Support\HelpHeaderAction;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use App\Services\StaffPick\MatchDispatchService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Provider-panel "Case Matches": the signed-in clinician's own assignment offers,
 * scoped strictly to the provider record they own (provider_id = their provider).
 * Reached from the provider avatar menu, never the sidebar. Mirrors the dashboard
 * MyOffers page (kept for admin/staff) and reuses its blade view.
 */
class MyOffers extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $slug = 'my-offers';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.provider.pages.my-offers';

    public function getTitle(): string|Htmlable
    {
        return __('Case Matches');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Respond within 24 hours to maintain your tier standing.');
    }

    public static function getNavigationLabel(): string
    {
        return __('Case Matches');
    }

    public static function canAccess(): bool
    {
        return static::resolveProvider() !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /**
     * Per-request memo of the resolved provider, keyed by "tenant:user".
     *
     * @var array<string, ?Provider>
     */
    protected static array $providerCache = [];

    /** The active/pending provider record owned by the current user, if any. */
    protected static function resolveProvider(): ?Provider
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->check()) {
            return null;
        }

        $key = $tenant->id.':'.auth()->id();

        if (! array_key_exists($key, static::$providerCache)) {
            static::$providerCache[$key] = Provider::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', auth()->id())
                ->whereIn('status', [Provider::STATUS_ACTIVE, Provider::STATUS_PENDING])
                ->first();
        }

        return static::$providerCache[$key];
    }

    /** @return Collection<int, AssignmentOffer> */
    public function pendingOffers(): Collection
    {
        $provider = static::resolveProvider();

        if ($provider === null) {
            return collect();
        }

        return AssignmentOffer::query()
            ->where('provider_id', $provider->id)
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->whereNotNull('offered_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['intakeRequest.discipline', 'intakeRequest.subject'])
            ->orderBy('offered_at')
            ->get();
    }

    /** @return Collection<int, AssignmentOffer> */
    public function expiredOffers(): Collection
    {
        $provider = static::resolveProvider();

        if ($provider === null) {
            return collect();
        }

        return AssignmentOffer::query()
            ->where('provider_id', $provider->id)
            ->where(function ($query) {
                $query->whereIn('status', [AssignmentOffer::STATUS_EXPIRED, AssignmentOffer::STATUS_WITHDRAWN])
                    ->orWhere(fn ($q) => $q
                        ->where('status', AssignmentOffer::STATUS_PENDING)
                        ->whereNotNull('offered_at')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now()));
            })
            ->with(['intakeRequest.discipline', 'intakeRequest.subject'])
            ->orderByDesc('offered_at')
            ->limit(25)
            ->get();
    }

    public function accept(int $offerId): void
    {
        $offer = $this->ownedPendingOffer($offerId);

        if ($offer === null || ($offer->expires_at !== null && $offer->expires_at->isPast())) {
            Notification::make()->title(__('This offer is no longer available'))->danger()->send();

            return;
        }

        app(MatchDispatchService::class)->handleAcceptance($offer->intakeRequest, $offer, auth()->user());

        $this->redirect(route('staffpick.offer.respond', ['token' => $offer->token]));
    }

    public function decline(int $offerId, int $declineReasonId): void
    {
        $offer = $this->ownedPendingOffer($offerId);

        if ($offer === null) {
            Notification::make()->title(__('This offer is no longer available'))->danger()->send();

            return;
        }

        app(MatchDispatchService::class)->handleRejection($offer->intakeRequest, $offer, $declineReasonId);

        Notification::make()->title(__('Offer declined'))->success()->send();
    }

    /** The decline modal: pick a reason, then hand off to decline(). */
    public function declineAction(): Action
    {
        return Action::make('decline')
            ->label(__('Decline'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->modalHeading(__('Decline offer'))
            ->schema([
                Select::make('decline_reason_id')
                    ->label(__('Reason'))
                    ->options(fn (): array => $this->declineReasonOptions())
                    ->required()
                    ->rules([
                        Rule::exists('sp_decline_reasons', 'id')
                            ->where('tenant_id', Filament::getTenant()?->id)
                            ->where('is_active', true),
                    ]),
            ])
            ->action(fn (array $arguments, array $data) => $this->decline((int) $arguments['offer'], (int) $data['decline_reason_id']));
    }

    /** @return array<int|string, string> */
    public function declineReasonOptions(): array
    {
        return DeclineReason::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function ownedPendingOffer(int $offerId): ?AssignmentOffer
    {
        $provider = static::resolveProvider();

        abort_if($provider === null, 403);

        return AssignmentOffer::query()
            ->where('id', $offerId)
            ->where('provider_id', $provider->id)
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->first();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [HelpHeaderAction::make('clinician/responding-to-offers')];
    }
}
