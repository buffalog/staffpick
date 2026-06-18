<x-filament-panels::page>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
        @foreach ($this->stats() as $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($stat['value']) }}</div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
