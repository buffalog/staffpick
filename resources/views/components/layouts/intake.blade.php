@php($skipCookieContentBar = true)
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.layouts.partials.head')
    @include('filament.dashboard.partials.leaflet-assets')
</head>
<body class="bg-gray-50 text-primary-900 dark:bg-gray-950" x-data>
    <div id="app">
        <div class="mx-auto my-6 max-w-3xl px-4 md:my-10">
            <div class="mb-6 flex items-center gap-3">
                <span class="text-lg font-semibold">{{ config('app.name') }}</span>
            </div>

            {{ $slot }}
        </div>

        @include('components.layouts.partials.tail')
    </div>
</body>
</html>
