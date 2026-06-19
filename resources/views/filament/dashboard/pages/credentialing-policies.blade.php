<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">
            {{ __('Set, per credential type, whether it is required, whether an expired credential automatically deactivates the provider, and how many days before expiry the warning alerts begin. Set the warning window to 0 to disable warnings for a type. Changes save automatically.') }}
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
