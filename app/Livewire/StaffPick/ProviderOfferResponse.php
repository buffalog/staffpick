<?php

namespace App\Livewire\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use App\Services\StaffPick\OfferService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Authenticated provider offer-response page (/offers/{token}). The token resolves
 * the offer; the page only loads if it belongs to the signed-in user's provider
 * record. Shows the full case (PHI) and accept/decline controls. Thin UI over
 * {@see OfferService}.
 */
#[Layout('components.layouts.intake')]
class ProviderOfferResponse extends Component
{
    #[Locked]
    public ?int $offerId = null;

    public bool $expired = false;

    public bool $responded = false;

    public ?string $outcome = null;

    public ?int $declineReasonId = null;

    public bool $decexpanded = false;

    public function mount(string $token): void
    {
        $offer = $this->resolveOffer($token);

        $this->offerId = $offer->id;
        $this->syncState($offer);
    }

    /**
     * Resolve and authorize the offer for the current user, or abort (404/403).
     */
    private function resolveOffer(string $token): AssignmentOffer
    {
        $offer = AssignmentOffer::withoutGlobalScopes()->where('token', $token)->first();

        abort_if($offer === null, 404);

        $provider = Provider::withoutGlobalScopes()
            ->where('tenant_id', $offer->tenant_id)
            ->where('user_id', auth()->id())
            ->first();

        // Cast both sides: pdo_sqlsrv (Railway) returns bigint columns as strings,
        // so a strict !== would reject the legitimate owner.
        abort_if($provider === null || (int) $provider->id !== (int) $offer->provider_id, 403);

        return $offer;
    }

    public function getOfferProperty(): AssignmentOffer
    {
        return AssignmentOffer::withoutGlobalScopes()
            ->with(['intakeRequest.subject', 'intakeRequest.discipline'])
            ->findOrFail($this->offerId);
    }

    /**
     * @return array<int|string, string>
     */
    public function declineReasonOptions(): array
    {
        return DeclineReason::withoutGlobalScopes()
            ->where('tenant_id', $this->offer->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function accept(): void
    {
        $offer = $this->actionableOffer();

        if ($offer === null) {
            return;
        }

        app(OfferService::class)->acceptOffer($offer, auth()->user());

        $this->responded = true;
        $this->outcome = 'accepted';
    }

    public function decline(): void
    {
        // The reason must be one of this tenant's active decline reasons — not just
        // any integer a crafted request could supply.
        $this->validate([
            'declineReasonId' => [
                'required', 'integer',
                Rule::exists('sp_decline_reasons', 'id')
                    ->where('tenant_id', $this->offer->tenant_id)
                    ->where('is_active', true),
            ],
        ]);

        $offer = $this->actionableOffer();

        if ($offer === null) {
            return;
        }

        app(OfferService::class)->declineOffer($offer, (int) $this->declineReasonId);

        $this->responded = true;
        $this->outcome = 'declined';
    }

    /**
     * Re-fetch and re-authorize the offer for a write, returning null (and updating
     * the view state) if it's no longer actionable.
     */
    private function actionableOffer(): ?AssignmentOffer
    {
        $offer = $this->offer;

        // Defense in depth: the token alone must never let another user respond.
        $provider = Provider::withoutGlobalScopes()
            ->where('tenant_id', $offer->tenant_id)
            ->where('user_id', auth()->id())
            ->first();

        // Cast both sides: pdo_sqlsrv (Railway) returns bigint columns as strings,
        // so a strict !== would reject the legitimate owner.
        abort_if($provider === null || (int) $provider->id !== (int) $offer->provider_id, 403);

        $this->syncState($offer);

        if ($this->responded || $this->expired) {
            return null;
        }

        return $offer;
    }

    private function syncState(AssignmentOffer $offer): void
    {
        $this->responded = in_array($offer->status, [
            AssignmentOffer::STATUS_ACCEPTED,
            AssignmentOffer::STATUS_DECLINED,
        ], true);

        $this->outcome = match ($offer->status) {
            AssignmentOffer::STATUS_ACCEPTED => 'accepted',
            AssignmentOffer::STATUS_DECLINED => 'declined',
            default => null,
        };

        $this->expired = in_array($offer->status, [
            AssignmentOffer::STATUS_EXPIRED,
            AssignmentOffer::STATUS_WITHDRAWN,
        ], true) || (
            $offer->status === AssignmentOffer::STATUS_PENDING
            && $offer->expires_at !== null
            && $offer->expires_at->isPast()
        );
    }

    /** Link back to the provider's My Offers dashboard page (tenant-scoped URL). */
    public function myOffersUrl(): ?string
    {
        $tenant = Tenant::find($this->offer->tenant_id);

        return $tenant ? url("/dashboard/{$tenant->uuid}/my-offers") : null;
    }

    public function render(): View
    {
        return view('livewire.staffpick.provider-offer-response');
    }
}
