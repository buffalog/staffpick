@props([
    'title',
    // Optional dot color (e.g. '#f59e0b' amber, '#ef4444' red) shown next to the title
    // when the section has notable content; false to hide.
    'dot' => false,
])

{{-- Flat, bordered accordion — the mocked design, not Filament's boxed+elevated Section.
     Styling lives once in provider-accordions.blade.php; this is just the structure.
     x-collapse (bundled by Filament) animates the body height and handles dynamic content
     (e.g. the embedded Credentials relation manager). --}}
<div x-data="{ open: false }" class="fi-sp-accordion">
    <button type="button" @click="open = ! open" :aria-expanded="open" class="fi-sp-accordion__header">
        <span class="fi-sp-accordion__label">
            {{ $title }}
            @if ($dot)
                <span class="fi-sp-accordion__dot" style="background: {{ $dot }};"></span>
            @endif
        </span>
        <svg class="fi-sp-accordion__chevron" :class="{ 'is-open': open }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
    <div x-show="open" x-collapse.duration.250ms x-cloak class="fi-sp-accordion__body">
        <div class="fi-sp-accordion__inner">
            {{ $slot }}
        </div>
    </div>
</div>
