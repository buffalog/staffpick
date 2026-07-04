@php
    /** @var \App\Models\StaffPick\Provider $provider */
    $provider = $getRecord();

    // Primary discipline first, then the rest.
    $disciplines = $provider->disciplines
        ->sortByDesc(fn ($discipline): bool => (bool) $discipline->pivot?->is_primary)
        ->values();
    $tier = $provider->tier;
    $window = $tier?->response_window_minutes;
    $languages = $provider->languages->pluck('name')->filter()->implode(', ');
    $photoUrl = filled($provider->photo)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($provider->photo)
        : null;
    $editUrl = \App\Filament\Dashboard\Resources\Providers\ProviderResource::getUrl('edit', ['record' => $provider]);
@endphp

<div class="fi-sp-provider-card flex h-full flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    {{-- Header: provider name only --}}
    <div class="px-4 pt-3 pb-2">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Provider profile') }}</div>
        <div class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ $provider->full_name }}</div>
    </div>

    {{-- Photo block --}}
    @if ($photoUrl)
        <img src="{{ $photoUrl }}" alt="{{ $provider->full_name }}" class="h-[140px] w-full object-cover" />
    @else
        <a href="{{ $editUrl }}"
           class="flex h-[140px] flex-col items-center justify-center gap-1 border-y-2 border-dashed border-gray-200 text-gray-400 transition hover:border-gray-300 hover:text-gray-500 dark:border-white/10 dark:text-gray-500">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
            <span class="text-xs font-medium">{{ __('Upload photo') }}</span>
        </a>
    @endif

    {{-- Badge chips: tier + one per discipline (same pill pattern) --}}
    <div class="flex flex-wrap items-center gap-1.5 px-4 pt-3">
        @if ($tier)
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ring-gray-950/10"
                  style="background-color: {{ $tier->color }}; color: #1f2937;">
                {{ $tier->name }}
            </span>
        @endif
        @foreach ($disciplines as $discipline)
            @php $chip = \App\Filament\Dashboard\Support\DisciplinePalette::forAbbreviation($discipline->abbreviation); @endphp
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ring-gray-950/10"
                  style="background-color: {{ $chip['bg'] }}; color: {{ $chip['text'] }};"
                  title="{{ $discipline->name }}">
                {{ $discipline->abbreviation ?: $discipline->name }}
            </span>
        @endforeach
    </div>

    {{-- Stat rows --}}
    <dl class="mt-3 flex-1 space-y-1.5 px-4 text-sm">
        <div class="flex items-center justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Response window') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $window !== null ? __(':n min', ['n' => $window]) : '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Preferred radius') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $provider->radius_preferred_miles !== null ? __(':n mi', ['n' => $provider->radius_preferred_miles]) : '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Max radius') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $provider->radius_max_miles !== null ? __(':n mi', ['n' => $provider->radius_max_miles]) : '—' }}</dd>
        </div>
        <div class="flex items-start justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Languages') }}</dt>
            <dd class="text-right font-medium text-gray-900 dark:text-white">{{ filled($languages) ? $languages : '—' }}</dd>
        </div>
    </dl>

    {{-- Footer: the real tier-cascade mechanic (Platinum is priority 1, offered first) --}}
    <div class="mt-3 border-t border-gray-100 px-4 py-2 text-[11px] leading-snug text-gray-400 dark:border-white/5 dark:text-gray-500">
        {{ __('Cases are offered by tier, Platinum first. Each provider holds the offer for their tier\'s response window before it passes to the next.') }}
    </div>
</div>
