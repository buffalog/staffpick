@php
    $card = 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    $label = 'block text-xs font-medium uppercase tracking-wide text-gray-400';
    $value = 'mt-0.5 text-sm text-gray-800 dark:text-gray-100';
    $offer = $this->offer;
    $intake = $offer->intakeRequest;
    $subject = $intake?->subject;
@endphp

<div class="mx-auto max-w-2xl space-y-6">
    @if ($expired)
        <div class="{{ $card }} text-center">
            <h1 class="text-xl font-semibold">{{ __('This offer has expired') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('This assignment offer is no longer available. Thank you.') }}
            </p>
        </div>
    @elseif ($responded && $outcome === 'accepted')
        <div class="{{ $card }} text-center">
            <h1 class="text-xl font-semibold text-success-600 dark:text-success-400">{{ __('Offer accepted') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Thank you. Our team will follow up with scheduling details shortly.') }}
            </p>
        </div>
    @elseif ($responded && $outcome === 'declined')
        <div class="{{ $card }} text-center">
            <h1 class="text-xl font-semibold">{{ __('Offer declined') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Thanks for letting us know. We will offer this case to another clinician.') }}
            </p>
        </div>
    @else
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Assignment offer') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Review the case below and accept or decline.') }}
            </p>
        </div>

        <div class="{{ $card }} space-y-4">
            <h2 class="text-base font-semibold">{{ __('Case details') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <span class="{{ $label }}">{{ __('Discipline') }}</span>
                    <p class="{{ $value }}">{{ $intake?->discipline?->name ?? __('Unspecified') }}</p>
                </div>
                <div>
                    <span class="{{ $label }}">{{ __('Patient') }}</span>
                    <p class="{{ $value }}">{{ trim("{$subject?->first_name} {$subject?->last_name}") }}</p>
                </div>
                <div class="md:col-span-2">
                    <span class="{{ $label }}">{{ __('Address') }}</span>
                    <p class="{{ $value }}">
                        {{ collect([$subject?->address, $subject?->address_2, $subject?->city, $subject?->state, $subject?->zip])->filter()->implode(', ') }}
                    </p>
                </div>
                @if ($intake?->start_date)
                    <div>
                        <span class="{{ $label }}">{{ __('Proposed start') }}</span>
                        <p class="{{ $value }}">{{ $intake->start_date->format('M j, Y') }}</p>
                    </div>
                @endif
                @if ($intake?->frequency)
                    <div>
                        <span class="{{ $label }}">{{ __('Frequency') }}</span>
                        <p class="{{ $value }}">{{ $intake->frequency }}</p>
                    </div>
                @endif
                <div>
                    <span class="{{ $label }}">{{ __('Distance') }}</span>
                    <p class="{{ $value }}">{{ $offer->distance_miles !== null ? $offer->distance_miles.' '.__('mi') : '—' }}</p>
                </div>
                @if ($intake?->notes)
                    <div class="md:col-span-2">
                        <span class="{{ $label }}">{{ __('Notes') }}</span>
                        <p class="{{ $value }}">{{ $intake->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="{{ $card }} space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" wire:click="accept" wire:loading.attr="disabled"
                        class="rounded-lg bg-success-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-success-700 disabled:opacity-50">
                    {{ __('Accept offer') }}
                </button>
                <button type="button" x-data x-on:click="$wire.decexpanded = true"
                        class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                    {{ __('Decline') }}
                </button>
            </div>

            @if ($decexpanded)
                <div class="space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Reason for declining') }} *</label>
                    <select wire:model="declineReasonId" class="w-full rounded-lg border border-gray-300 p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">{{ __('Select a reason') }}</option>
                        @foreach ($this->declineReasonOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('declineReasonId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="button" wire:click="decline" wire:loading.attr="disabled"
                            class="mt-2 rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900 disabled:opacity-50">
                        {{ __('Submit decline') }}
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
