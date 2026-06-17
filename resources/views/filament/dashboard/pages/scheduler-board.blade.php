<x-filament-panels::page>
    @php
        $board = $this->getBoard();
        $labels = $this->columnLabels();
        $needs = $this->getNeedsAttention();
        $needsCount = $needs['no_clinicians_available']->count() + $needs['cancelled']->count();
        $createUrl = \App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource::getUrl('create');
    @endphp

    <div x-data="spSchedulerBoard()" wire:poll.30s class="flex flex-col gap-6">
        {{-- Last-updated indicator --}}
        <div
            class="flex items-center gap-2 text-xs text-gray-400"
            x-data="{ updatedAt: Date.now(), ago: 0 }"
            x-init="setInterval(() => ago = Math.max(0, Math.round((Date.now() - updatedAt) / 1000)), 1000)"
            x-effect="updatedAt = $wire.lastUpdatedAt ? Date.parse($wire.lastUpdatedAt) : updatedAt"
        >
            <span>{{ __('Updated') }} <span x-text="ago">0</span>{{ __('s ago') }}</span>
            <span wire:loading class="text-gray-300">· {{ __('refreshing…') }}</span>
        </div>

        {{-- Board columns --}}
        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach ($board as $status => $cards)
                <div wire:key="board-col-{{ $status }}" class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-50 dark:bg-white/5">
                    <div class="flex items-center justify-between gap-2 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $labels[$status] }}</span>
                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $cards->count() }}</span>
                        </div>
                        @if ($status === 'pending')
                            <a href="{{ $createUrl }}" class="text-primary-600 transition hover:text-primary-500" title="{{ __('New intake') }}">
                                <x-filament::icon icon="heroicon-o-plus-circle" class="h-5 w-5" />
                            </a>
                        @endif
                    </div>

                    <div
                        data-board-dropzone
                        data-status="{{ $status }}"
                        wire:key="board-zone-{{ $status }}"
                        class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-3"
                    >
                        @forelse ($cards as $card)
                            @include('filament.dashboard.partials.board-card', ['card' => $card, 'draggable' => true])
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-400 dark:border-white/10">
                                {{ __('No cases') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Needs Attention --}}
        <div x-data="{ open: {{ $needsCount > 0 ? 'true' : 'false' }} }" class="rounded-xl border border-gray-200 dark:border-white/10">
            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between px-4 py-3">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('Needs Attention') }}
                    <span class="ml-1 text-gray-400">({{ $needsCount }})</span>
                </span>
                <x-filament::icon icon="heroicon-o-chevron-down" x-bind:class="open ? 'rotate-180' : ''" class="h-5 w-5 text-gray-400 transition" />
            </button>

            <div x-show="open" x-collapse class="grid gap-4 px-4 pb-4 md:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('No Clinicians Available') }}</h3>
                    <div class="flex flex-col gap-2">
                        @forelse ($needs['no_clinicians_available'] as $card)
                            @include('filament.dashboard.partials.board-card', ['card' => $card, 'retrigger' => true])
                        @empty
                            <div class="text-xs text-gray-400">{{ __('Nothing here.') }}</div>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Cancelled') }}</h3>
                    <div class="flex flex-col gap-2">
                        @forelse ($needs['cancelled'] as $card)
                            @include('filament.dashboard.partials.board-card', ['card' => $card])
                        @empty
                            <div class="text-xs text-gray-400">{{ __('Nothing here.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
