<x-button-link.default
    {{ $attributes }}
    {{ $attributes->merge(['class' => 'border border-secondary-600 text-secondary-800 bg-transparent hover:bg-secondary-50 focus:ring-secondary-300 dark:bg-secondary-600 dark:hover:bg-secondary-700 dark:focus:ring-secondary-800']) }}
>
    {{ $slot }}
</x-button-link.default>
