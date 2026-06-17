@php
    $url = filled($token) ? route('staffpick.slack.inbound', ['token' => $token]) : null;
@endphp

@if ($url)
    <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-2">
        <code class="break-all text-sm text-gray-700 dark:text-gray-200">{{ $url }}</code>
        <button
            type="button"
            x-on:click="navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
        >
            <span x-show="!copied">{{ __('Copy') }}</span>
            <span x-show="copied" x-cloak class="text-success-600 dark:text-success-400">{{ __('Copied!') }}</span>
        </button>
    </div>
@else
    <span class="text-sm text-gray-500">{{ __('Not generated yet — generate a token to enable inbound.') }}</span>
@endif
