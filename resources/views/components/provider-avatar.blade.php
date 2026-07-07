@props(['provider', 'size' => 64])

@php
    /** @var \App\Models\StaffPick\Provider $provider */
    // Photo metadata only (never the BLOB): the eager-loaded relation if present, else one
    // cheap query. A versioned URL (?v=updated_at) lets the response cache for a week yet
    // bust the moment a photo is replaced.
    $photo = $provider->relationLoaded('photo')
        ? $provider->photo
        : $provider->photo()->select('id', 'provider_id', 'mime_type', 'updated_at')->first();

    $url = $photo
        ? route('staffpick.provider-photos.show', ['provider' => $provider->id, 'v' => $photo->updated_at?->getTimestamp()])
        : null;

    // Initials fallback, colored by primary discipline — same palette the cards use
    // (teal PT/PTA, coral OT/OTA, purple SLP, slate default).
    $palette = \App\Filament\Dashboard\Support\DisciplinePalette::forAbbreviation($provider->discipline?->abbreviation);
    $initials = collect([$provider->first_name, $provider->last_name])
        ->filter()
        ->map(fn ($n) => mb_strtoupper(mb_substr(trim((string) $n), 0, 1)))
        ->implode('');
    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr(trim((string) ($provider->business_name ?: $provider->full_name)), 0, 1)) ?: '?';
    }
    $px = (int) $size;
@endphp

@if ($url)
    <img
        src="{{ $url }}"
        alt="{{ $provider->full_name }}"
        loading="lazy"
        {{ $attributes->merge(['class' => 'rounded-full object-cover']) }}
        style="width: {{ $px }}px; height: {{ $px }}px;"
    />
@else
    <span
        aria-label="{{ $provider->full_name }}"
        {{ $attributes->merge(['class' => 'inline-flex select-none items-center justify-center rounded-full font-semibold']) }}
        style="width: {{ $px }}px; height: {{ $px }}px; font-size: {{ (int) round($px * 0.4) }}px; background-color: {{ $palette['bg'] }}; color: {{ $palette['text'] }};"
    >{{ $initials }}</span>
@endif
