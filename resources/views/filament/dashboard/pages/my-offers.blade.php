<x-filament-panels::page>
    @php
        $pending = $this->pendingOffers();
        $expired = $this->expiredOffers();
        $card = 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900';
        $label = 'text-xs font-medium uppercase tracking-wide text-gray-400';
    @endphp

    @if ($pending->isEmpty())
        <div class="{{ $card }} text-center">
            <h2 class="text-lg font-semibold">{{ __('No pending offers') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                {{ __('You have no offers waiting for a response right now. Keep your profile, availability, and service area up to date so we can match you to more cases.') }}
            </p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($pending as $offer)
                @php
                    $intake = $offer->intakeRequest;
                    $secondsLeft = ($offer->expires_at && $offer->expires_at->isFuture()) ? now()->diffInSeconds($offer->expires_at) : 0;
                @endphp
                <div class="{{ $card }}" wire:key="offer-{{ $offer->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-3">
                            <div>
                                <div class="{{ $label }}">{{ __('Discipline') }}</div>
                                <div class="text-sm font-medium">{{ $intake?->discipline?->name ?? __('Unspecified') }}</div>
                            </div>
                            <div>
                                <div class="{{ $label }}">{{ __('Area') }}</div>
                                <div class="text-sm font-medium">{{ $intake?->subject?->city ?? __('—') }}</div>
                            </div>
                            @if ($intake?->start_date)
                                <div>
                                    <div class="{{ $label }}">{{ __('Proposed start') }}</div>
                                    <div class="text-sm font-medium">{{ $intake->start_date->format('M j, Y') }}</div>
                                </div>
                            @endif
                            <div>
                                <div class="{{ $label }}">{{ __('Offered') }}</div>
                                <div class="text-sm font-medium">{{ $offer->offered_at?->diffForHumans() }}</div>
                            </div>
                            <div>
                                <div class="{{ $label }}">{{ __('Expires in') }}</div>
                                <div class="text-sm font-medium tabular-nums"
                                     x-data="{ left: {{ $secondsLeft }} }"
                                     x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
                                    <span x-text="left > 0 ? (Math.floor(left/60) + 'm ' + (left%60) + 's') : '{{ __('expired') }}'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-filament::button color="success" wire:click="accept({{ $offer->id }})" wire:loading.attr="disabled">
                                {{ __('Accept') }}
                            </x-filament::button>
                            <x-filament::button color="gray" wire:click="mountAction('decline', { offer: {{ $offer->id }} })">
                                {{ __('Decline') }}
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($expired->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">{{ __('Expired') }}</h2>
            <div class="space-y-3">
                @foreach ($expired as $offer)
                    @php $intake = $offer->intakeRequest; @endphp
                    <div class="{{ $card }} opacity-60" wire:key="expired-{{ $offer->id }}">
                        <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                            <span><span class="{{ $label }}">{{ __('Discipline') }}</span> {{ $intake?->discipline?->name ?? __('Unspecified') }}</span>
                            <span><span class="{{ $label }}">{{ __('Area') }}</span> {{ $intake?->subject?->city ?? __('—') }}</span>
                            <span class="text-gray-400">{{ __('This offer is no longer available.') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
