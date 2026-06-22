<x-filament-panels::page>
    @if ($this->provider() === null)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
            <x-filament::icon
                icon="heroicon-o-user-circle"
                class="mx-auto mb-3 h-10 w-10 text-gray-400"
            />
            <p class="text-base font-medium text-gray-950 dark:text-white">
                {{ __('No provider profile linked to your account.') }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Contact your administrator.') }}
            </p>
        </div>
    @else
        {{-- FullCalendar global build (CDN). Loaded once here; the calendar widget
             initialises against window.FullCalendar. --}}
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

        @livewire(\App\Filament\Provider\Widgets\MyCasesCalendar::class)

        {{ $this->table }}
    @endif
</x-filament-panels::page>
