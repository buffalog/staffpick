@props([
    'options' => [],
    'model',
    'placeholder' => null,
    'inputClass' => '',
])

@php($noPreference = $placeholder ?? __('No preference'))

{{-- Searchable combobox over a list of language names. Stores the selected NAME
     (not an id) into the given Livewire model path, and reverts free text that
     isn't a valid option. Self-contained Alpine — no JS dependency. --}}
<div
    x-data="{
        options: @js($options),
        selected: @entangle($model),
        query: '',
        open: false,
        init() { this.query = this.selected ?? ''; },
        matches() {
            const q = (this.query ?? '').trim().toLowerCase();
            if (q === '' || q === (this.selected ?? '').toLowerCase()) { return this.options; }
            return this.options.filter(o => o.toLowerCase().includes(q));
        },
        choose(value) { this.selected = value; this.query = value; this.open = false; },
        close() { this.open = false; if (this.query !== (this.selected ?? '')) { this.query = this.selected ?? ''; } },
    }"
    @click.outside="close()"
    class="relative"
>
    <input
        type="text"
        x-model="query"
        @focus="open = true"
        @click="open = true"
        @keydown.escape.stop="close()"
        placeholder="{{ $noPreference }}"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open"
        class="{{ $inputClass }}"
    >
    <ul
        x-show="open"
        style="display: none"
        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-600 dark:bg-gray-800"
    >
        <li @click="choose('')" class="cursor-pointer px-3 py-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">{{ $noPreference }}</li>
        <template x-for="option in matches()" :key="option">
            <li
                @click="choose(option)"
                x-text="option"
                :class="option === selected ? 'bg-primary-50 font-medium dark:bg-gray-700' : ''"
                class="cursor-pointer px-3 py-2 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700"
            ></li>
        </template>
        <li x-show="matches().length === 0" class="px-3 py-2 text-gray-400">{{ __('No matches') }}</li>
    </ul>
</div>
