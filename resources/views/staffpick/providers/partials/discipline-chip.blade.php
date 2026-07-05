{{-- Single discipline pill, colored via DisciplinePalette. Props: $abbreviation, $name.
     The one source of truth for the discipline chip — reused by the card grid and the
     provider detail header. --}}
@php $chip = \App\Filament\Dashboard\Support\DisciplinePalette::forAbbreviation($abbreviation ?? null); @endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ring-gray-950/10"
      style="background-color: {{ $chip['bg'] }}; color: {{ $chip['text'] }};"
      @if (! empty($name)) title="{{ $name }}" @endif>
    {{ $abbreviation ?: ($name ?? '') }}
</span>
