<x-filament-panels::page>
    @php
        $oldest = $this->oldestPending();
        $lastSourceDays = $this->lastSourceAddedDaysAgo();
    @endphp

    {{-- Section 1: oldest-pending alert banner --}}
    @if ($oldest)
        @php $days = $this->daysWaiting($oldest); @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-400/30 dark:bg-amber-400/10">
            <div class="flex items-center gap-3 text-sm text-amber-800 dark:text-amber-200">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6 shrink-0 text-amber-500" />
                <span>
                    <span class="font-semibold">{{ __('Longest Pending:') }}</span>
                    {{ $oldest->reference_number ?: "#{$oldest->id}" }}
                    · {{ $oldest->subject?->last_name ?? __('—') }}
                    · {{ trans_choice('{1} :count day|[2,*] :count days', $days, ['count' => $days]) }}
                    · {{ $oldest->discipline?->name ?? __('—') }}
                    · {{ $oldest->referralSource?->name ?? __('—') }}
                </span>
            </div>
            <a href="{{ $this->findMatchesUrl($oldest) }}">
                <x-filament::button icon="heroicon-o-sparkles" tag="span">{{ __('Find Matches →') }}</x-filament::button>
            </a>
        </div>
    @else
        <div class="flex items-center gap-3 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300">
            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-green-500" />
            {{ __('All cases assigned') }}
        </div>
    @endif

    {{-- Section 2: stat cards --}}
    @livewire(\App\Filament\Dashboard\Widgets\StaffDashboardStats::class)

    {{-- Section 3: quick-action cards --}}
    <div class="grid gap-4 md:grid-cols-2">
        {{-- Providers --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Providers') }}</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ trans_choice('{1} :count active provider|[2,*] :count active providers', $this->activeProviderCount(), ['count' => $this->activeProviderCount()]) }}
                · {{ trans_choice('{0} no credential alerts|{1} :count credential alert|[2,*] :count credential alerts', $this->credentialAlertCount(), ['count' => $this->credentialAlertCount()]) }}
            </p>
            <ul class="mt-3 space-y-1.5 text-sm">
                @forelse ($this->recentProviders() as $provider)
                    <li class="flex items-center justify-between gap-2">
                        <span class="text-gray-700 dark:text-gray-200">{{ trim("{$provider->first_name} {$provider->last_name}") }}</span>
                        <span class="flex items-center gap-2 text-xs text-gray-500">
                            {{ $provider->discipline?->name ?? '—' }}
                            @if ($provider->tier?->name)
                                <x-filament::badge color="gray">{{ $provider->tier->name }}</x-filament::badge>
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="text-xs text-gray-400">{{ __('No providers yet') }}</li>
                @endforelse
            </ul>
            <div class="mt-4">
                <x-filament::dropdown placement="bottom-start">
                    <x-slot name="trigger">
                        <x-filament::button icon="heroicon-o-plus" tag="span">{{ __('Add Provider') }}</x-filament::button>
                    </x-slot>

                    <x-filament::dropdown.list>
                        <x-filament::dropdown.list.item
                            tag="a"
                            href="{{ $this->addProviderUrl() }}"
                            icon="heroicon-o-pencil-square"
                        >
                            {{ __('Onboard Manually') }}
                        </x-filament::dropdown.list.item>

                        <x-filament::dropdown.list.item
                            icon="heroicon-o-link"
                            x-on:click="
                                window.navigator.clipboard.writeText(@js($this->applicationLinkUrl()));
                                new FilamentNotification().title(@js(__('Application link copied'))).success().send();
                            "
                        >
                            {{ __('Copy Application Link') }}
                        </x-filament::dropdown.list.item>
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            </div>
        </x-filament::section>

        {{-- Referral Sources --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Referral Sources') }}</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ trans_choice('{1} :count source|[2,*] :count sources', $this->sourceCount(), ['count' => $this->sourceCount()]) }}
                @if ($lastSourceDays !== null)
                    · {{ __('Last added :days days ago', ['days' => $lastSourceDays]) }}
                @endif
            </p>
            <ul class="mt-3 space-y-1.5 text-sm">
                @forelse ($this->recentSources() as $source)
                    <li class="flex items-center justify-between gap-2">
                        <span class="text-gray-700 dark:text-gray-200">{{ $source->name }}</span>
                        <span class="text-xs text-gray-500">{{ $source->city ?? '—' }}</span>
                    </li>
                @empty
                    <li class="text-xs text-gray-400">{{ __('No referral sources yet') }}</li>
                @endforelse
            </ul>
            <div class="mt-4">
                <a href="{{ $this->addSourceUrl() }}">
                    <x-filament::button icon="heroicon-o-plus" tag="span">{{ __('Add Referral Source') }}</x-filament::button>
                </a>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
