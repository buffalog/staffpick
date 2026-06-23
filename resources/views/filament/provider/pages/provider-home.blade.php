<x-filament-panels::page>
    @if ($this->provider() === null)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
            <x-filament::icon icon="heroicon-o-user-circle" class="mx-auto mb-3 h-10 w-10 text-gray-400" />
            <p class="text-base font-medium text-gray-950 dark:text-white">
                {{ __('No provider profile linked to your account.') }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Contact your administrator.') }}
            </p>
        </div>
    @else
        @php
            $tier = $this->tierBlock();
            $alerts = $this->credentialAlerts();
            $counts = $this->caseCounts();
            $tierClasses = match ($tier['color']) {
                'amber' => 'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                'gray' => 'bg-gray-50 border-gray-300 text-gray-700 dark:bg-gray-500/10 dark:text-gray-300',
                'indigo' => 'bg-indigo-50 border-indigo-300 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300',
                default => 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
            };
        @endphp

        <div class="grid gap-4 md:grid-cols-3">
            {{-- Block 1: Tier --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Tier') }}</x-slot>
                <div class="flex items-center">
                    <span class="inline-flex items-center rounded-lg border px-3 py-1.5 text-lg font-semibold {{ $tierClasses }}">
                        {{ $tier['name'] ?? __('No tier assigned') }}
                    </span>
                </div>
            </x-filament::section>

            {{-- Block 2: Credential alerts --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Credential Alerts') }}</x-slot>
                @if (empty($alerts))
                    <div class="flex items-center gap-2 text-sm font-medium text-success-600 dark:text-success-400">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                        {{ __('All credentials current') }}
                    </div>
                @else
                    <ul class="space-y-2">
                        @foreach ($alerts as $alert)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ $alert['type'] }}
                                    <span class="text-gray-400">— {{ $alert['expires'] }}</span>
                                </span>
                                <x-filament::badge :color="$alert['expired'] ? 'danger' : 'warning'">
                                    {{ $alert['expired'] ? __('Expired') : __('Expiring Soon') }}
                                </x-filament::badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            {{-- Block 3: Case counts --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Cases') }}</x-slot>
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ $counts['active'] }}</div>
                        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Active') }}</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ $counts['closed'] }}</div>
                        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Closed') }}</div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{ $this->table }}
    @endif
</x-filament-panels::page>
