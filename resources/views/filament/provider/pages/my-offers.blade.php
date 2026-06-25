{{--
    Provider-panel Case Matches. Built from Filament-native components + inline styles
    only: the provider panel has no compiled Tailwind theme, so arbitrary utility
    classes (grid-cols-*, gap-*, rounded-xl, bg-white…) don't render here. x-filament
    components and inline styles are theme-independent.
--}}
<x-filament-panels::page>
    @php
        $pending = $this->pendingOffers();
        $expired = $this->expiredOffers();
        $metaGrid = 'display:grid; grid-template-columns:max-content 1fr; gap:0.5rem 2rem; font-size:0.875rem; align-items:center;';
        $metaLabel = 'color:rgb(107 114 128);';
    @endphp

    @if ($pending->isEmpty())
        <x-filament::section>
            <div style="text-align:center; padding:2rem 1rem;">
                <div style="font-size:2.5rem; line-height:1; color:rgb(156 163 175); margin-bottom:0.75rem;">
                    <x-filament::icon icon="heroicon-o-inbox" style="width:2.5rem; height:2.5rem; margin:0 auto;" />
                </div>
                <p style="font-weight:600;">{{ __('No pending case matches.') }}</p>
            </div>
        </x-filament::section>
    @else
        <div style="display:flex; flex-direction:column; gap:1rem;">
            @foreach ($pending as $offer)
                @php
                    $intake = $offer->intakeRequest;
                    $discipline = $intake?->discipline?->name ?? __('Unspecified');
                    $area = $intake?->subject?->city ?? __('—');
                    $isExpired = $offer->expires_at && $offer->expires_at->isPast();
                @endphp
                <x-filament::section wire:key="offer-{{ $offer->id }}">
                    <x-slot name="heading">{{ $discipline }} · {{ $area }}</x-slot>

                    <div style="{{ $metaGrid }}">
                        <span style="{{ $metaLabel }}">{{ __('Proposed Start') }}</span>
                        <span>{{ $intake?->start_date?->format('M j, Y') ?? __('—') }}</span>

                        <span style="{{ $metaLabel }}">{{ __('Offered') }}</span>
                        <span>{{ $offer->offered_at?->diffForHumans() ?? __('—') }}</span>

                        <span style="{{ $metaLabel }}">{{ __('Time Remaining') }}</span>
                        <span>
                            @if ($isExpired)
                                <x-filament::badge color="danger">{{ __('Expired') }}</x-filament::badge>
                            @elseif ($offer->expires_at)
                                <span x-data="{
                                    label: '',
                                    init() {
                                        const expires = {{ $offer->expires_at->timestamp }} * 1000;
                                        const update = () => {
                                            const ms = expires - Date.now();
                                            if (ms <= 0) { this.label = '{{ __('Expired') }}'; return; }
                                            const tm = Math.floor(ms / 60000), s = Math.floor((ms % 60000) / 1000), h = Math.floor(tm / 60), m = tm % 60;
                                            this.label = h > 0 ? (h + 'h ' + m + 'm') : (m + 'm ' + s + 's');
                                        };
                                        update();
                                        setInterval(update, 1000);
                                    }
                                }" x-text="label" style="font-variant-numeric:tabular-nums; font-weight:600;"></span>
                            @else
                                {{ __('—') }}
                            @endif
                        </span>
                    </div>

                    <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                        <x-filament::button color="success" wire:click="accept({{ $offer->id }})" wire:loading.attr="disabled">
                            {{ __('Accept') }}
                        </x-filament::button>
                        <x-filament::button color="gray" wire:click="mountAction('decline', { offer: {{ $offer->id }} })">
                            {{ __('Decline') }}
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    @if ($expired->isNotEmpty())
        <div style="margin-top:1.5rem;">
            <h3 style="font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:rgb(156 163 175); margin-bottom:0.75rem;">
                {{ __('Expired') }}
            </h3>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                @foreach ($expired as $offer)
                    @php $intake = $offer->intakeRequest; @endphp
                    <x-filament::section wire:key="expired-{{ $offer->id }}">
                        <div style="display:flex; flex-wrap:wrap; gap:0.25rem 2rem; font-size:0.875rem; opacity:0.7;">
                            <span><span style="{{ $metaLabel }}">{{ __('Discipline') }}</span> {{ $intake?->discipline?->name ?? __('Unspecified') }}</span>
                            <span><span style="{{ $metaLabel }}">{{ __('Area') }}</span> {{ $intake?->subject?->city ?? __('—') }}</span>
                            <span style="color:rgb(156 163 175);">{{ __('This offer is no longer available.') }}</span>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
