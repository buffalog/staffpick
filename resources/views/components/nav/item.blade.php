@props(['route' => '#'])

@php($selected = request()->routeIs($route))
@php($selectedClass = $selected ? 'text-primary-400' : 'text-primary-900')

<li {{ $attributes }}>
    <a href="{{ str_starts_with($route, '#') ? (route('home') . $route) : route($route) }}" class="text-sm font-medium block py-2 px-3 md:p-0 rounded hover:bg-primary-50 md:hover:bg-transparent md:hover:text-primary-600 md:dark:hover:text-primary-500 dark:text-white dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700 {{ $selectedClass }}">
        {{ $slot }}
    </a>
</li>
