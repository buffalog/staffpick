<x-filament-panels::page>
    {{--
        Auto-save: the wrapper listens for any field change bubbling up from the form,
        debounces, and persists the wizard's current state as a draft via $wire.autoSave().
        Filament's text inputs use deferred wire:model, so the action call flushes the
        latest typed values along with it. The current wizard step (read from the
        wizard's Alpine state) is passed so a return visit resumes in place.
    --}}
    <div
        x-data="{
            saveStatus: 'idle',
            wizardStep() {
                const wizard = $el.querySelector('.fi-sc-wizard');
                if (! wizard) return null;
                const data = Alpine.$data(wizard);
                return data.getStepIndex(data.step) + 1;
            },
            autoSave() {
                this.saveStatus = 'saving';
                $wire.autoSave(this.wizardStep()).then(() => { this.saveStatus = 'saved'; });
            },
        }"
        x-on:input.debounce.600ms="autoSave()"
        x-on:change.debounce.600ms="autoSave()"
    >
        <div class="mb-3 flex h-5 items-center justify-end text-sm">
            <span x-show="saveStatus === 'saving'" x-cloak class="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                <x-filament::loading-indicator class="h-4 w-4" />
                {{ __('Saving…') }}
            </span>
            <span x-show="saveStatus === 'saved'" x-cloak class="flex items-center gap-1.5 text-success-600 dark:text-success-400">
                @svg('heroicon-m-check-circle', 'h-4 w-4')
                {{ __('Saved') }}
            </span>
        </div>

        <form wire:submit="submit">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
