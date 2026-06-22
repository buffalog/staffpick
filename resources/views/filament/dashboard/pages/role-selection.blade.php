<x-filament-panels::page>
    <div class="mx-auto max-w-3xl">
        <p class="mb-6 text-center text-base text-gray-600 dark:text-gray-400">
            {{ __('Tell us how you\'ll be using StaffPick so we can take you to the right place.') }}
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- I'm a Clinician (Provider) --}}
            <button
                type="button"
                wire:click="becomeProvider"
                wire:loading.attr="disabled"
                class="group flex flex-col items-center rounded-xl border-2 border-indigo-200 bg-white p-8 text-center transition hover:border-indigo-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 dark:border-indigo-900/60 dark:bg-gray-900 dark:hover:border-indigo-500"
            >
                <span class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                    <x-filament::icon icon="heroicon-o-user-circle" class="h-9 w-9" />
                </span>
                <span class="text-lg font-bold text-gray-950 dark:text-white">
                    {{ __('I\'m a Clinician') }}
                </span>
                <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('I provide therapy services and want to receive assignment offers.') }}
                </span>
            </button>

            {{-- I'm a Referring Clinic (Referrer) --}}
            <button
                type="button"
                wire:click="becomeReferrer"
                wire:loading.attr="disabled"
                class="group flex flex-col items-center rounded-xl border-2 border-emerald-200 bg-white p-8 text-center transition hover:border-emerald-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-60 dark:border-emerald-900/60 dark:bg-gray-900 dark:hover:border-emerald-500"
            >
                <span class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-filament::icon icon="heroicon-o-building-office-2" class="h-9 w-9" />
                </span>
                <span class="text-lg font-bold text-gray-950 dark:text-white">
                    {{ __('I\'m a Referring Clinic') }}
                </span>
                <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('I refer patients and want to submit and track intake requests.') }}
                </span>
            </button>
        </div>

        <p class="mt-8 text-center text-xs text-gray-500 dark:text-gray-500">
            {{ __('Your selection can be changed later by an administrator.') }}
        </p>
    </div>
</x-filament-panels::page>
