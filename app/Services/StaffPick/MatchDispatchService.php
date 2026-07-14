<?php

namespace App\Services\StaffPick;

use App\Mail\StaffPick\OfferAvailable;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\User;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Tier-cascade dispatch engine. Replaces the sequential OfferService queue: instead of
 * pre-queuing every eligible provider, it computes the single best provider each cycle
 * and cascades on timeout / rejection, escalating to staff when the pool is exhausted.
 *
 * Eligibility is owned by {@see MatchingEngine} (hard filters); ordering by the swappable
 * {@see ProviderScorer}. Offer delivery carries NO PHI — a bell + preferred-channel nudge
 * to /offers/{token}, same as the retired OfferService.
 */
class MatchDispatchService
{
    /** Geo relaxation for a manual force-match (stub — full criteria relaxation is a later spec). */
    private const FORCE_MATCH_RADIUS_MILES = 100000.0;

    /** Fallback window when a provider has no tier (or the tier has no window set). */
    private const DEFAULT_WINDOW_MINUTES = 30;

    public function __construct(
        private MatchingEngine $engine,
        private ProviderScorer $scorer,
        private MatchNotificationService $notifications,
        private SmsService $sms,
    ) {}

    /**
     * Find the next best available provider and send them an offer; escalate if none.
     *
     * @param  bool  $forceMatch  staff override from an ESCALATED card — bypasses the
     *                            geo filter (stub). Eligibility relaxation rules land later.
     * @param  array<int, int>  $excludeProviderIds  providers this run already lost a race on
     *                                               (see the guard in sendOffer) — skip them.
     */
    public function dispatch(IntakeRequest $case, bool $forceMatch = false, array $excludeProviderIds = []): void
    {
        $ordered = $this->eligibleProviders($case, $forceMatch);

        // (2) Skip providers tied up with an open offer on a different case.
        // map to int explicitly: these lists are strict-compared against $provider->id
        // below, and pdo_sqlsrv would otherwise leak raw strings through a future
        // un-cast read (see AssignmentOffer::casts()).
        $busyProviderIds = AssignmentOffer::query()
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->whereNotNull('offered_at')
            ->where('intake_request_id', '!=', $case->id)
            ->pluck('provider_id')
            ->map(intval(...))
            ->all();

        // (3) Skip providers already tried on THIS case (expired or rejected/declined).
        $triedProviderIds = $case->assignmentOffers()
            ->whereIn('status', [AssignmentOffer::STATUS_EXPIRED, AssignmentOffer::STATUS_DECLINED])
            ->pluck('provider_id')
            ->map(intval(...))
            ->all();

        // (4) Top remaining.
        $provider = $ordered
            ->reject(fn (Provider $p): bool => in_array($p->id, $busyProviderIds, true))
            ->reject(fn (Provider $p): bool => in_array($p->id, $triedProviderIds, true))
            ->reject(fn (Provider $p): bool => in_array((int) $p->id, $excludeProviderIds, true))
            ->first();

        if ($provider === null) {
            $this->escalate($case, MatchingEngine::blockingReason($case));

            return;
        }

        if ($this->sendOffer($case, $provider, guarded: true) !== null) {
            return;
        }

        // Lost the race under the lock. Either a concurrent dispatch already opened an offer
        // on this case (nothing to do — one open offer per case), or the provider got taken
        // by another case's dispatch, in which case cascade past them.
        if ($this->openOffer($case) === null) {
            $excludeProviderIds[] = (int) $provider->id;

            $this->dispatch($case, $forceMatch, $excludeProviderIds);
        }
    }

    /**
     * The open offer's window elapsed: expire it, count the attempt, and cascade.
     * ProcessMatchTimeouts calls this; dispatch()'s already-tried filter prevents
     * re-offering the same provider.
     */
    public function handleTimeout(IntakeRequest $case): void
    {
        $offer = $this->openOffer($case);

        if ($offer !== null) {
            $offer->update([
                'status' => AssignmentOffer::STATUS_EXPIRED,
                'response' => AssignmentOffer::STATUS_EXPIRED,
                'expired_at' => now(),
                'responded_at' => now(),
            ]);
        }

        $case->update([
            'cascade_attempt' => $case->cascade_attempt + 1,
            'current_match_provider_id' => null,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);

        $this->notifyStaff($case, MatchNotificationService::EVENT_TIMEOUT, __('Offer timed out'), __('No response from the matched provider — cascading to the next.'));

        $this->dispatch($case);
    }

    /**
     * Provider declined: mark the offer rejected (= declined), count the attempt, and
     * cascade. MATCH_REJECTED is transient — set, then immediately UNMATCHED.
     */
    public function handleRejection(IntakeRequest $case, AssignmentOffer $offer, ?int $declineReasonId = null): void
    {
        // Defense in depth: only persist a decline reason that belongs to the offer's tenant.
        $reasonId = $declineReasonId !== null
            ? DeclineReason::query()->where('tenant_id', $offer->tenant_id)->whereKey($declineReasonId)->value('id')
            : null;

        $offer->update([
            'status' => AssignmentOffer::STATUS_DECLINED, // "rejected" === declined (single lifecycle field)
            'response' => AssignmentOffer::STATUS_DECLINED,
            'decline_reason_id' => $reasonId !== null ? (int) $reasonId : null,
            'responded_at' => now(),
        ]);

        $case->update([
            'cascade_attempt' => $case->cascade_attempt + 1,
            'current_match_provider_id' => null,
            'status' => IntakeRequest::STATUS_MATCH_REJECTED,
        ]);
        $case->update(['status' => IntakeRequest::STATUS_UNMATCHED]);

        $this->notifyStaff($case, MatchNotificationService::EVENT_REJECTED, __('Offer declined'), __('The matched provider declined — cascading to the next.'));

        $this->dispatch($case);
    }

    /**
     * Provider accepted: they become the lead clinician and the case is MATCHED. An
     * Assignment row is created so the provider portal's caseload (which queries
     * assignments) keeps working. MATCH_ACCEPTED is transient → MATCHED.
     */
    public function handleAcceptance(IntakeRequest $case, AssignmentOffer $offer, ?User $actor = null): void
    {
        DB::transaction(function () use ($case, $offer, $actor): void {
            $offer->update([
                'status' => AssignmentOffer::STATUS_ACCEPTED,
                'response' => AssignmentOffer::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            // Withdraw any other still-open offers for this case.
            $case->assignmentOffers()
                ->whereKeyNot($offer->id)
                ->where('status', AssignmentOffer::STATUS_PENDING)
                ->update(['status' => AssignmentOffer::STATUS_WITHDRAWN, 'responded_at' => now()]);

            $case->assignments()->where('is_current', true)->update(['is_current' => false]);

            Assignment::create([
                'tenant_id' => $offer->tenant_id,
                'intake_request_id' => $offer->intake_request_id,
                'provider_id' => $offer->provider_id,
                'status' => Assignment::STATUS_PENDING,
                'is_current' => true,
                'is_manual' => false,
                'assigned_by_user_id' => $actor?->id,
                'assigned_at' => now(),
                'offered_at' => $offer->offered_at,
                'responded_at' => now(),
            ]);

            $case->update(['status' => IntakeRequest::STATUS_MATCH_ACCEPTED]);
            $case->update([
                'status' => IntakeRequest::STATUS_MATCHED,
                'lead_clinician_id' => $offer->provider_id,
                'current_match_provider_id' => null,
                'assigned_at' => now(),
            ]);
        });

        $this->notifyStaff($case, MatchNotificationService::EVENT_ACCEPTED, __('Match accepted'), __('A provider accepted and is now the lead clinician.'));
    }

    /**
     * Send an offer to one manually-chosen provider (the Find Matches modal), bypassing
     * scoring. Same offer creation + no-PHI delivery as the cascade.
     */
    public function offerTo(IntakeRequest $case, Provider $provider): void
    {
        $this->sendOffer($case, $provider);
    }

    /** MatchingEngine for eligibility, ProviderScorer for order. forceMatch relaxes geo (stub). */
    private function eligibleProviders(IntakeRequest $case, bool $forceMatch): Collection
    {
        $radiusOverride = $forceMatch ? self::FORCE_MATCH_RADIUS_MILES : null;

        $providers = $this->engine->match($case, $radiusOverride)
            ->map(fn (MatchingResult $result): Provider => $result->provider);

        return $this->scorer->order($case, $providers);
    }

    /**
     * Create + deliver one offer.
     *
     * @param  bool  $guarded  serialize the pick against concurrent dispatches (AUTO path).
     *                         Returns null when the race was lost and nothing was sent. The
     *                         manual offerTo() path is unguarded — staff override wins.
     */
    private function sendOffer(IntakeRequest $case, Provider $provider, bool $guarded = false): ?AssignmentOffer
    {
        $tier = $provider->tier;
        $windowMinutes = $tier?->response_window_minutes ?? self::DEFAULT_WINDOW_MINUTES;
        $languageWarning = $this->languageWarning($case, $provider);

        $offer = DB::transaction(function () use ($case, $provider, $tier, $windowMinutes, $languageWarning, $guarded): ?AssignmentOffer {
            // Take an exclusive lock on the provider row (SQL Server UPDLOCK) so two
            // concurrent dispatches can't both read "free" and both offer them, then
            // re-validate under it: the busy/open-offer reads dispatch() made are stale
            // the moment another cascade commits an offer.
            if ($guarded) {
                Provider::query()->whereKey($provider->id)->lockForUpdate()->first();

                $taken = AssignmentOffer::query()
                    ->where('status', AssignmentOffer::STATUS_PENDING)
                    ->where(fn ($query) => $query
                        ->where('provider_id', $provider->id)
                        ->orWhere('intake_request_id', $case->id))
                    ->exists();

                if ($taken) {
                    return null;
                }
            }

            // MATCH_MADE is transient — set, then immediately advanced to MATCH_SENT.
            $case->update(['status' => IntakeRequest::STATUS_MATCH_MADE]);

            $offer = AssignmentOffer::create([
                'tenant_id' => $case->tenant_id,
                'intake_request_id' => $case->id,
                'provider_id' => $provider->id,
                'offer_sequence' => (int) $case->assignmentOffers()->max('offer_sequence') + 1,
                'status' => AssignmentOffer::STATUS_PENDING,
                'tier_at_offer' => $tier?->name,
                'response_window_minutes' => $windowMinutes,
                'language_warning' => $languageWarning,
                'offered_at' => now(),
                'expires_at' => now()->addMinutes($windowMinutes),
                'delivery_channel' => $provider->preferred_contact_channel ?: Provider::CHANNEL_EMAIL,
                'token' => Str::random(48),
            ]);

            $case->update([
                'status' => IntakeRequest::STATUS_MATCH_SENT,
                'current_match_provider_id' => $provider->id,
                'last_match_sent_at' => now(),
            ]);

            return $offer;
        });

        if ($offer === null) {
            return null;
        }

        // Deliver outside the transaction — SMS is a synchronous external call.
        $this->deliverOffer($offer);

        return $offer;
    }

    /**
     * True when the subject stated a language preference this provider does not speak.
     *
     * Computed per offer rather than copied from MatchingEngine's pool-level flag: it must
     * hold for the manual offerTo() path too, and stay correct when a requested non-speaker
     * is offered while speakers exist in the pool.
     */
    private function languageWarning(IntakeRequest $case, Provider $provider): bool
    {
        $case->loadMissing('subject');
        $provider->loadMissing('languages');

        $preference = $case->subject?->language_preference;

        return filled($preference) && ! MatchingEngine::languageMatches(
            $preference,
            $provider->languages->flatMap(fn (Language $language): array => [$language->name, $language->code])->all(),
        );
    }

    /**
     * Escalate with the REAL reason. An ungeocoded (or discipline-less) case returns an
     * empty pool for a structural reason, and telling staff "pool exhausted" sends them
     * to check provider availability when the fix is the subject's address.
     */
    private function escalate(IntakeRequest $case, ?string $reason = null): void
    {
        $reason ??= IntakeRequest::ESCALATION_POOL_EXHAUSTED;

        $case->update([
            'status' => IntakeRequest::STATUS_ESCALATED,
            'escalation_reason' => $reason,
            'escalated_at' => now(),
            'current_match_provider_id' => null,
        ]);

        $this->notifyStaff($case, MatchNotificationService::EVENT_ESCALATED, __('Case escalated'), IntakeRequest::escalationReasonLabel($reason));
    }

    private function openOffer(IntakeRequest $case): ?AssignmentOffer
    {
        return $case->assignmentOffers()
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->whereNotNull('offered_at')
            ->latest('offered_at')
            ->first();
    }

    /**
     * NO-PHI delivery: a Filament bell to the provider's user + a nudge over their
     * preferred channel, both pointing at /offers/{token}. Full details require login.
     */
    private function deliverOffer(AssignmentOffer $offer): void
    {
        $offer->loadMissing(['provider.user', 'intakeRequest.discipline', 'intakeRequest.subject']);
        $provider = $offer->provider;
        $intake = $offer->intakeRequest;

        if ($provider === null) {
            return;
        }

        $discipline = $intake?->discipline?->name ?? __('Unspecified');
        $city = $intake?->subject?->city ?? __('your area');
        $url = route('staffpick.offer.respond', ['token' => $offer->token]);

        if ($provider->user instanceof User) {
            Notification::make()
                ->title(__('New assignment offer'))
                ->body(__(':discipline in :city — sign in to review and respond.', ['discipline' => $discipline, 'city' => $city]))
                ->actions([NotificationAction::make('respond')->label(__('Review offer'))->url($url)->markAsRead()])
                ->sendToDatabase($provider->user);
        }

        match ($offer->delivery_channel) {
            Provider::CHANNEL_SMS => filled($provider->phone)
                ? $this->sms->send($provider->phone, __('New assignment offer: :discipline in :city. Sign in to respond: :url', ['discipline' => $discipline, 'city' => $city, 'url' => $url]))
                : null,
            Provider::CHANNEL_PORTAL => null,
            default => filled($provider->email)
                ? Mail::to($provider->email)->queue(new OfferAvailable($offer))
                : null,
        };
    }

    /** Staff alert for a cascade event — per-channel/per-user gated by MatchNotificationService. */
    private function notifyStaff(IntakeRequest $case, string $event, string $heading, string $body): void
    {
        $this->notifications->notify($case, $event, $heading, $body);
    }
}
