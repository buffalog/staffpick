<?php

namespace App\Services\StaffPick;

use App\Mail\StaffPick\AssignmentConfirmedReferrer;
use App\Mail\StaffPick\OfferAvailable;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\TenantConfig;
use App\Models\User;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The sequential assignment-offer pipeline. Builds a ranked offer queue for an intake
 * from the matching engine, sends one offer at a time (delivering with NO PHI), and
 * advances on accept / decline / expiry. On acceptance an assignment is created and
 * the rest of the queue is withdrawn; on exhaustion the case is flagged for the
 * scheduler.
 */
class OfferService
{
    public function __construct(
        private MatchingEngine $engine,
        private SchedulerNotificationService $scheduler,
        private SmsService $sms,
    ) {}

    /**
     * Build (or rebuild, for a re-trigger) the ranked offer queue and send the first
     * offer. A null match set flags the case as having no clinicians available.
     */
    public function dispatchOffers(IntakeRequest $intake, ?float $radiusOverrideMiles = null): void
    {
        // Withdraw any still-open offers from a previous run before re-queuing.
        $intake->assignmentOffers()
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->update(['status' => AssignmentOffer::STATUS_WITHDRAWN, 'responded_at' => now()]);

        $results = $this->engine->match($intake, $radiusOverrideMiles);

        if ($results->isEmpty()) {
            $this->markNoClinicians($intake);

            return;
        }

        $sequence = 1;

        foreach ($results as $result) {
            AssignmentOffer::create([
                'tenant_id' => $intake->tenant_id,
                'intake_request_id' => $intake->id,
                'provider_id' => $result->provider->id,
                'offer_sequence' => $sequence++,
                'distance_miles' => round($result->distanceMiles, 2),
                'match_score' => round($result->score, 4),
                'status' => AssignmentOffer::STATUS_PENDING,
                'delivery_channel' => $result->provider->preferred_contact_channel ?: Provider::CHANNEL_EMAIL,
                'token' => Str::random(48),
            ]);
        }

        $intake->update(['status' => 'offered', 'matched_at' => now()]);

        $this->sendNextOffer($intake);
    }

    /**
     * Send the next queued (not-yet-sent) offer for an intake, or flag the case when
     * the queue is exhausted. Returns the offer that was sent, or null.
     */
    public function sendNextOffer(IntakeRequest $intake): ?AssignmentOffer
    {
        $offer = $intake->assignmentOffers()
            ->where('status', AssignmentOffer::STATUS_PENDING)
            ->whereNull('offered_at')
            ->orderBy('offer_sequence')
            ->first();

        if ($offer === null) {
            $this->markNoClinicians($intake);

            return null;
        }

        $this->sendOffer($offer);

        return $offer;
    }

    public function acceptOffer(AssignmentOffer $offer, User $actor): Assignment
    {
        $intake = $offer->intakeRequest;
        $created = false;

        $assignment = DB::transaction(function () use ($offer, $intake, $actor, &$created): Assignment {
            // Lock the offer row and re-check it's still open, so a double-submit or
            // race can't create two assignments for the same offer.
            $locked = AssignmentOffer::query()->whereKey($offer->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== AssignmentOffer::STATUS_PENDING) {
                $existing = $intake->assignments()->where('is_current', true)->first();

                if ($existing !== null) {
                    return $existing;
                }

                throw new RuntimeException('This offer is no longer available.');
            }

            $locked->update([
                'status' => AssignmentOffer::STATUS_ACCEPTED,
                'response' => AssignmentOffer::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            $intake->assignments()->where('is_current', true)->update(['is_current' => false]);

            $assignment = Assignment::create([
                'tenant_id' => $locked->tenant_id,
                'intake_request_id' => $locked->intake_request_id,
                'provider_id' => $locked->provider_id,
                'status' => Assignment::STATUS_PENDING,
                'is_current' => true,
                'is_manual' => false,
                'assigned_by_user_id' => $actor->id,
                'assigned_at' => now(),
                'offered_at' => $locked->offered_at,
                'responded_at' => now(),
            ]);

            // Withdraw every other still-open offer for this intake.
            $intake->assignmentOffers()
                ->whereKeyNot($locked->id)
                ->where('status', AssignmentOffer::STATUS_PENDING)
                ->update(['status' => AssignmentOffer::STATUS_EXPIRED, 'responded_at' => now()]);

            $intake->update(['status' => 'assigned_pending', 'assigned_at' => now()]);

            $created = true;

            return $assignment;
        });

        // Only notify when this call actually created the assignment (not on a lost race).
        if ($created) {
            $this->scheduler->notifyAssignmentAccepted($assignment);

            if (filled($intake->referralSource?->email)) {
                Mail::to($intake->referralSource->email)->queue(new AssignmentConfirmedReferrer($intake));
            }
        }

        return $assignment;
    }

    public function declineOffer(AssignmentOffer $offer, int $declineReasonId): ?AssignmentOffer
    {
        $offer->update([
            'status' => AssignmentOffer::STATUS_DECLINED,
            'response' => AssignmentOffer::STATUS_DECLINED,
            'decline_reason_id' => $declineReasonId,
            'responded_at' => now(),
        ]);

        return $this->sendNextOffer($offer->intakeRequest);
    }

    /**
     * Expire a sent offer that timed out and advance the queue. Used by CheckOfferExpiry.
     */
    public function expireOffer(AssignmentOffer $offer): void
    {
        $offer->update([
            'status' => AssignmentOffer::STATUS_EXPIRED,
            'response' => AssignmentOffer::STATUS_EXPIRED,
            'responded_at' => now(),
        ]);

        $this->sendNextOffer($offer->intakeRequest);
    }

    private function sendOffer(AssignmentOffer $offer): void
    {
        $delay = TenantConfig::query()->where('tenant_id', $offer->tenant_id)->first()?->offerDelaySeconds() ?? 300;

        $offer->update([
            'offered_at' => now(),
            'expires_at' => now()->addSeconds($delay),
            'status' => AssignmentOffer::STATUS_PENDING,
        ]);

        $this->deliverOffer($offer);
    }

    /**
     * Deliver an offer with NO PHI: a Filament bell to the provider's user account
     * plus a message through their preferred channel. Full case details are only
     * reachable after login at /offers/{token}.
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
            Provider::CHANNEL_PORTAL => null, // in-app bell only
            default => filled($provider->email)
                ? Mail::to($provider->email)->queue(new OfferAvailable($offer))
                : null,
        };
    }

    private function markNoClinicians(IntakeRequest $intake): void
    {
        $intake->update(['status' => 'no_clinicians_available']);

        $this->scheduler->notifyNoClinicians($intake);
    }
}
