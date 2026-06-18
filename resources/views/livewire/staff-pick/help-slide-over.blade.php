<div>
    @php $content = $this->content(); @endphp

    <div
        x-data="{ open: @entangle('open') }"
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="open = false"
        class="fixed inset-0 z-[60]"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/40" x-on:click="open = false"></div>

        {{-- Panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-gray-900"
        >
            <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <div class="flex min-w-0 items-center gap-2">
                    <x-filament::icon icon="heroicon-o-lifebuoy" class="h-5 w-5 shrink-0 text-primary-500" />
                    <span class="truncate font-semibold text-gray-900 dark:text-white">{{ $content['title'] ?? __('Help') }}</span>
                </div>
                <button type="button" wire:click="close" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 dark:hover:bg-white/5" title="{{ __('Close') }}">
                    <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                </button>
            </div>

            <div
                class="flex-1 overflow-y-auto px-5 py-4"
                x-on:click="
                    const a = $event.target.closest('a');
                    if (a) {
                        const href = a.getAttribute('href') || '';
                        if (! /^(https?:|mailto:|#|\/)/.test(href)) {
                            $event.preventDefault();
                            $wire.goToSlug(href);
                        }
                    }
                "
            >
                @if ($content)
                    <x-help-doc :html="$content['html']" />
                @else
                    <div class="text-sm text-gray-500">{{ __('Help topic not found.') }}</div>
                @endif
            </div>

            @if ($content && $this->helpCenterUrl())
                <div class="border-t border-gray-200 px-5 py-3 dark:border-white/10">
                    <a href="{{ $this->helpCenterUrl() }}" wire:navigate class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        {{ __('Open full help center →') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
