@php
    /** @var \App\Models\StaffPick\Provider $provider */
    $provider = $record;
    // Band tinted to the PRIMARY discipline; accent border + rounded/overflow treatment
    // identical to the card grid (the corner-gap fix). Name and chips live in ONE flex row
    // on the same plane (no seam) — name left, chips + tier right. Chips keep their ring so
    // a same-discipline chip still reads against the matching band tint.
    $palette = \App\Filament\Dashboard\Support\DisciplinePalette::forAbbreviation($provider->discipline?->abbreviation);
    $disciplines = $provider->disciplines
        ->sortByDesc(fn ($discipline): bool => (bool) $discipline->pivot?->is_primary)
        ->values();
@endphp

<div class="overflow-hidden rounded-xl border-2 bg-white shadow-sm dark:bg-gray-900" style="border-color: {{ $palette['text'] }};">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" style="background-color: {{ $palette['bg'] }}; color: {{ $palette['text'] }};">
        <div class="text-lg font-semibold">{{ $provider->full_name }}</div>
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($disciplines as $discipline)
                @include('staffpick.providers.partials.discipline-chip', ['abbreviation' => $discipline->abbreviation, 'name' => $discipline->name])
            @endforeach
            @include('staffpick.providers.partials.tier-badge', ['tier' => $provider->tier])
        </div>
    </div>
</div>
