@php
    // The $entangle() calls live in the x-data expression (not @js'd config)
    // because $wire is only in scope there, and Alpine must process the returned
    // interceptors at init time to turn them into live values. marker mode binds
    // lat/lng/geocode_failed; polygon mode binds the service-zone points array.
    // See spLeafletMap() in the dashboard head partial.
    $hint = $mode === 'marker'
        ? __('Drag the pin to set your exact location, or click the map to drop it where you are.')
        : __('Use the polygon tool (top-left of the map) to outline your service area. Click to add points, click the first point to finish.');
@endphp

<div
    x-data="spLeafletMap({
        mode: '{{ $mode }}',
        @if ($mode === 'marker')
        lat: $wire.$entangle('{{ $latModel }}'),
        lng: $wire.$entangle('{{ $lngModel }}'),
        failed: $wire.$entangle('{{ $failedModel }}'),
        @else
        points: $wire.$entangle('{{ $pointsModel }}'),
        @endif
    })"
    data-sp-leaflet="{{ $mode }}"
    class="space-y-2"
>
    <div
        wire:ignore
        x-ref="map"
        class="z-0 h-80 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
        style="min-height: 20rem;"
    ></div>

    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $hint }}</p>
</div>
