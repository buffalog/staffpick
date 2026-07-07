@php
    /** @var \App\Models\StaffPick\Provider $provider */
    $provider = $getRecord();

    // Header band is keyed to the PRIMARY discipline pointer (discipline_id) only — a
    // dual-discipline provider gets their primary's color, not a blend. The body chips
    // below remain the full source of truth for every discipline held.
    $headerPalette = \App\Filament\Dashboard\Support\DisciplinePalette::forAbbreviation($provider->discipline?->abbreviation);

    // Primary discipline first, then the rest.
    $disciplines = $provider->disciplines
        ->sortByDesc(fn ($discipline): bool => (bool) $discipline->pivot?->is_primary)
        ->values();
    $tier = $provider->tier;
    $window = $tier?->response_window_minutes;
    $languages = $provider->languages->pluck('name')->filter()->implode(', ');

    // Photo metadata only (never the BLOB); versioned URL so a replacement busts the cache.
    $photo = $provider->relationLoaded('photo')
        ? $provider->photo
        : $provider->photo()->select('id', 'provider_id', 'updated_at')->first();
    $photoUrl = $photo
        ? route('staffpick.provider-photos.show', ['provider' => $provider->id, 'v' => $photo->updated_at?->getTimestamp()])
        : null;
@endphp

<div class="fi-sp-provider-card flex h-full flex-col overflow-hidden rounded-xl border-2 bg-white pb-4 shadow-sm dark:bg-gray-900"
     style="border-color: {{ $headerPalette['text'] }};">
    {{-- Header: provider name only, in a band tinted to the primary discipline. No radius
         here — overflow-hidden on the card clips it to the shared rounded corners. Filament
         wraps this first child in an <a class="fi-ta-record-content"> with 0 16px padding;
         -mx-4 cancels that horizontal inset so the band bleeds to the border on all edges.
         px-8 (not px-4) then insets the name 16px on both sides — the extra 16px compensates
         the -mx-4 bleed — so the name lines up with the px-4 body content below it. --}}
    <div class="-mx-4 px-8 pt-3 pb-2" style="background-color: {{ $headerPalette['bg'] }}; color: {{ $headerPalette['text'] }};">
        <div class="truncate text-base font-semibold">{{ $provider->full_name }}</div>
    </div>

    {{-- Photo block: the uploaded photo as a banner, or the discipline-colored initials
         avatar centered in the same slot when there's no photo. --}}
    @if ($photoUrl)
        <img src="{{ $photoUrl }}" alt="{{ $provider->full_name }}" loading="lazy" class="h-[140px] w-full object-cover" />
    @else
        <div class="flex h-[140px] items-center justify-center border-y-2 border-gray-100 dark:border-white/5">
            <x-provider-avatar :provider="$provider" :size="88" />
        </div>
    @endif

    {{-- Discipline chip(s): one per discipline held, on the top row --}}
    <div class="flex flex-wrap items-center gap-1.5 px-4 pt-3">
        @foreach ($disciplines as $discipline)
            @include('staffpick.providers.partials.discipline-chip', ['abbreviation' => $discipline->abbreviation, 'name' => $discipline->name])
        @endforeach
    </div>

    {{-- Tier badge on its own line, directly below the discipline chips --}}
    @if ($tier)
        <div class="px-4 pt-2">
            @include('staffpick.providers.partials.tier-badge', ['tier' => $tier])
        </div>
    @endif

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
</div>
