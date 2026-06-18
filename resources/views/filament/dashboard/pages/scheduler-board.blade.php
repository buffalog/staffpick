<x-filament-panels::page>
    @php
        $board = $this->getBoard();
        $labels = $this->columnLabels();
        $needs = $this->getNeedsAttention();
        $needsCount = $needs['no_clinicians_available']->count() + $needs['cancelled']->count();
        $stats = $this->boardStats($board, $needs);
        $createUrl = \App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource::getUrl('create');
        $tenantName = \Filament\Facades\Filament::getTenant()?->name;

        // Per-status accent: header left-border, count badge, and a low-opacity header tint.
        $colColors = [
            'pending' => ['accent' => 'border-l-slate-400', 'badge' => 'bg-slate-200 text-slate-700', 'tint' => 'bg-slate-50'],
            'matching' => ['accent' => 'border-l-blue-500', 'badge' => 'bg-blue-100 text-blue-700', 'tint' => 'bg-blue-50'],
            'offered' => ['accent' => 'border-l-amber-500', 'badge' => 'bg-amber-100 text-amber-700', 'tint' => 'bg-amber-50'],
            'assigned_pending' => ['accent' => 'border-l-violet-500', 'badge' => 'bg-violet-100 text-violet-700', 'tint' => 'bg-violet-50'],
            'active' => ['accent' => 'border-l-green-500', 'badge' => 'bg-green-100 text-green-700', 'tint' => 'bg-green-50'],
            'on_hold' => ['accent' => 'border-l-red-500', 'badge' => 'bg-red-100 text-red-700', 'tint' => 'bg-red-50'],
            'completed' => ['accent' => 'border-l-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700', 'tint' => 'bg-emerald-50'],
        ];
    @endphp

    {{-- isFull overlays the board above Filament chrome (z-30) but below modals (z-40+). Esc exits. --}}
    <div
        x-data="{ isFull: false, statsOpen: true }"
        x-on:keydown.escape.window="isFull = false"
        :class="isFull ? 'fixed inset-0 z-[35] overflow-auto p-4' : 'rounded-xl p-4'"
        class="flex flex-col gap-4 bg-slate-50"
    >
        {{-- Board header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ __('Dispatch Board') }}</h1>
                @if ($tenantName)
                    <p class="text-sm text-gray-500">{{ $tenantName }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Stat chips --}}
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-green-100 px-3 py-1.5 text-sm font-semibold text-green-700">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    {{ $stats['total_active'] }} {{ __('active') }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold {{ $needsCount > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4" />
                    {{ $needsCount }} {{ __('attention') }}
                </span>
                <span
                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-1.5 text-xs text-gray-500"
                    x-data="{ updatedAt: Date.now(), ago: 0 }"
                    x-init="setInterval(() => ago = Math.max(0, Math.round((Date.now() - updatedAt) / 1000)), 1000)"
                    x-effect="updatedAt = $wire.lastUpdatedAt ? Date.parse($wire.lastUpdatedAt) : updatedAt"
                >
                    <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                    <span x-text="ago">0</span>{{ __('s ago') }}
                    <span wire:loading class="text-gray-400">·</span>
                </span>

                {{-- Actions --}}
                <a href="{{ $createUrl }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                    <x-filament::icon icon="heroicon-o-plus-circle" class="h-4 w-4" />
                    {{ __('New Intake') }}
                </a>
                <button
                    type="button"
                    x-on:click="statsOpen = ! statsOpen"
                    title="{{ __('Toggle stats panel') }}"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white p-1.5 text-gray-600 transition hover:bg-gray-50"
                >
                    <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    x-on:click="isFull = ! isFull"
                    title="{{ __('Full screen (Esc to exit)') }}"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white p-1.5 text-gray-600 transition hover:bg-gray-50"
                >
                    <span x-show="! isFull"><x-filament::icon icon="heroicon-o-arrows-pointing-out" class="h-5 w-5" /></span>
                    <span x-show="isFull" style="display: none;"><x-filament::icon icon="heroicon-o-arrows-pointing-in" class="h-5 w-5" /></span>
                </button>
            </div>
        </div>

        {{-- Board + right stats panel --}}
        <div x-data="spSchedulerBoard()" wire:poll.30s class="flex gap-4">
            {{-- Columns --}}
            <div class="min-w-0 flex-1 overflow-x-auto pb-2">
                <div class="flex gap-4" :class="isFull ? 'min-h-[calc(100vh-9rem)]' : ''">
                    @foreach ($board as $status => $cards)
                        @php $c = $colColors[$status] ?? $colColors['pending']; @endphp
                        <div wire:key="board-col-{{ $status }}" class="flex w-[280px] shrink-0 flex-col rounded-xl bg-gray-100">
                            {{-- Column header --}}
                            <div class="rounded-t-xl border-l-4 {{ $c['accent'] }} {{ $c['tint'] }} px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-800">{{ $labels[$status] }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $c['badge'] }}">{{ $cards->count() }}</span>
                                </div>
                                <div class="mt-0.5 text-[11px] text-gray-500">{{ $cards->count() }} {{ \Illuminate\Support\Str::plural(__('case'), $cards->count()) }}</div>
                            </div>

                            {{-- Dropzone --}}
                            <div
                                data-board-dropzone
                                data-status="{{ $status }}"
                                wire:key="board-zone-{{ $status }}"
                                class="flex min-h-24 flex-1 flex-col gap-2 p-2"
                            >
                                @forelse ($cards as $card)
                                    @include('filament.dashboard.partials.board-card', ['card' => $card, 'draggable' => $this->isDraggableStatus($status)])
                                @empty
                                    <div class="rounded-lg border border-dashed border-gray-300 px-3 py-6 text-center text-xs text-gray-400">{{ __('No cases') }}</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Right stats panel --}}
            <aside x-show="statsOpen" class="w-[200px] shrink-0 space-y-3">
                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Active cases') }}</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['total_active'] }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('By discipline') }}</div>
                    <div class="mt-2 space-y-1.5">
                        @foreach (['PT' => 'bg-blue-500', 'OT' => 'bg-teal-500', 'SLP' => 'bg-violet-500'] as $d => $dot)
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-gray-600"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ $d }}</span>
                                <span class="font-semibold text-gray-900">{{ $stats['by_discipline'][$d] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Offered') }}</div>
                    <div class="mt-1 text-2xl font-bold text-amber-600">{{ $stats['offered'] }}</div>
                    <div class="text-[11px] text-gray-400">{{ __('pending provider response') }}</div>
                </div>

                <div class="rounded-xl border p-3 shadow-sm {{ $needsCount > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-white' }}">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Needs attention') }}</div>
                    <div class="mt-1 text-2xl font-bold {{ $needsCount > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $needsCount }}</div>
                </div>
            </aside>
        </div>

        {{-- Needs Attention warning band --}}
        <div x-show="! isFull" x-data="{ open: {{ $needsCount > 0 ? 'true' : 'false' }} }" class="rounded-xl border border-amber-200 bg-amber-50">
            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-2 px-4 py-3">
                <span class="flex items-center gap-2 text-sm font-semibold text-amber-800">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-amber-500" />
                    {{ __('Needs Attention') }}
                    <span class="rounded-full bg-amber-200 px-2 py-0.5 text-xs text-amber-800">{{ $needsCount }}</span>
                </span>
                <x-filament::icon icon="heroicon-o-chevron-down" class="h-5 w-5 text-amber-500 transition" x-bind:class="open ? 'rotate-180' : ''" />
            </button>

            <div x-show="open" x-collapse class="grid gap-4 px-4 pb-4 md:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('No Clinicians Available') }}</h3>
                    <div class="flex flex-col gap-2">
                        @forelse ($needs['no_clinicians_available'] as $card)
                            @include('filament.dashboard.partials.board-card', ['card' => $card, 'retrigger' => true])
                        @empty
                            <div class="text-xs text-amber-700/70">{{ __('Nothing here.') }}</div>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('Cancelled') }}</h3>
                    <div class="flex flex-col gap-2">
                        @forelse ($needs['cancelled'] as $card)
                            @include('filament.dashboard.partials.board-card', ['card' => $card])
                        @empty
                            <div class="text-xs text-amber-700/70">{{ __('Nothing here.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
