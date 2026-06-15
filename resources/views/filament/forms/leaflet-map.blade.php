@php
    // Built by the calling schema component via ->viewData([...]).
    // marker mode entangles lat/lng/geocode_failed; polygon mode entangles the
    // service-zone points array. See spLeafletMap() in the dashboard head partial.
    $config = $mode === 'marker'
        ? [
            'mode' => 'marker',
            'latModel' => $latModel,
            'lngModel' => $lngModel,
            'failedModel' => $failedModel,
        ]
        : [
            'mode' => 'polygon',
            'pointsModel' => $pointsModel,
        ];

    $hint = $mode === 'marker'
        ? __('Drag the pin to set your exact location, or click the map to drop it where you are.')
        : __('Use the polygon tool (top-left of the map) to outline your service area. Click to add points, click the first point to finish.');
@endphp

<div
    x-data="spLeafletMap(@js($config))"
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
