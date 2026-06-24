@php
    $card = 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $input = 'mt-1 w-full rounded-lg border border-gray-300 p-2 text-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100';
@endphp

<div>
    @if ($submitted)
        <div class="{{ $card }} text-center">
            <h1 class="text-xl font-semibold">{{ __('Registration received') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Thank you for registering. Our team will review your details and be in touch shortly.') }}
            </p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            {{-- Agency --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Agency Information') }}</h2>
                <div>
                    <label class="{{ $label }}">{{ __('Agency name') }} *</label>
                    <input type="text" wire:model="data.name" class="{{ $input }}">
                    @error('data.name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('Contact name') }} *</label>
                        <input type="text" wire:model="data.contact_name" class="{{ $input }}">
                        @error('data.contact_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Portal username') }}</label>
                        <input type="text" wire:model="data.portal_username" class="{{ $input }}">
                        @error('data.portal_username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Contact --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Contact') }}</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">{{ __('Phone') }} *</label>
                        <input type="text" wire:model="data.phone" class="{{ $input }}">
                        @error('data.phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Fax') }}</label>
                        <input type="text" wire:model="data.fax" class="{{ $input }}">
                        @error('data.fax') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Email') }} *</label>
                        <input type="email" wire:model="data.email" class="{{ $input }}">
                        @error('data.email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Address') }}</h2>
                <div>
                    <label class="{{ $label }}">{{ __('Street address') }}</label>
                    <input type="text" wire:model="data.address" class="{{ $input }}">
                    @error('data.address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">{{ __('City') }}</label>
                        <input type="text" wire:model="data.city" class="{{ $input }}">
                        @error('data.city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('State') }}</label>
                        <input type="text" wire:model="data.state" class="{{ $input }}">
                        @error('data.state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('ZIP') }}</label>
                        <input type="text" wire:model="data.zip" class="{{ $input }}">
                        @error('data.zip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span wire:loading class="text-sm text-gray-500">{{ __('Submitting…') }}</span>
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                    {{ __('Register') }}
                </button>
            </div>
        </form>
    @endif
</div>
