<x-filament-panels::page>
    @php
        $sections = $this->sections();
        $current = $this->currentTopic();
        $searching = $this->isSearching();
        $results = $searching ? $this->searchResults() : [];
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row">
        {{-- Sidebar --}}
        <aside class="w-full shrink-0 lg:w-72">
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $this->roleLabel() }}</div>

                {{-- Search --}}
                <div class="relative mb-4">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="query"
                        placeholder="{{ __('Search help…') }}"
                        class="w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                {{-- Navigation --}}
                <nav class="flex flex-col gap-4">
                    @foreach ($sections as $section)
                        <div>
                            <div class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $section['title'] }}</div>
                            <ul class="flex flex-col gap-0.5">
                                @foreach ($section['topics'] as $topic)
                                    @php $active = ! $searching && $this->topic === $topic['slug']; @endphp
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="selectTopic('{{ $topic['slug'] }}')"
                                            class="w-full rounded-lg px-2 py-1.5 text-left text-sm transition {{ $active ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' }}"
                                        >
                                            {{ $topic['title'] }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Content --}}
        <div class="min-w-0 flex-1">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                @if ($searching)
                    <div class="mb-4 text-sm text-gray-500">
                        {{ trans_choice('{0}No results for ":q"|{1}1 result for ":q"|[2,*]:count results for ":q"', count($results), ['q' => $this->query, 'count' => count($results)]) }}
                    </div>
                    <div class="flex flex-col gap-2">
                        @foreach ($results as $result)
                            <button
                                type="button"
                                wire:click="selectTopic('{{ $result['slug'] }}')"
                                class="rounded-lg border border-gray-200 p-3 text-left transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-white/10 dark:hover:bg-white/5"
                            >
                                <div class="text-xs text-gray-400">{{ $result['section'] }}</div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $result['title'] }}</div>
                                <div class="mt-1 text-sm text-gray-500">{{ $result['snippet'] }}</div>
                            </button>
                        @endforeach
                    </div>
                @elseif ($current)
                    {{-- Intra-doc links (bare slugs) navigate within the help center. --}}
                    {{-- Intra-doc links (bare slugs) navigate within the help center. --}}
                    <div
                        x-data
                        x-on:click="
                            const a = $event.target.closest('a');
                            if (a) {
                                const href = a.getAttribute('href') || '';
                                if (! /^(https?:|mailto:|#|\/)/.test(href)) {
                                    $event.preventDefault();
                                    $wire.selectTopic(href);
                                }
                            }
                        "
                    >
                        <x-help-doc :html="$current['html']" />
                    </div>
                @else
                    <div class="text-sm text-gray-500">{{ __('Select a topic from the menu.') }}</div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
