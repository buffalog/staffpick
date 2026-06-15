@php
    $card = 'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $input = 'mt-1 w-full rounded-lg border border-gray-300 p-2 text-sm focus:border-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100';
@endphp

<div>
    @if ($submitted)
        <div class="{{ $card }} text-center">
            <h1 class="text-xl font-semibold">{{ __('Referral received') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Thank you. Our intake team will be in touch shortly. Please keep your reference number for your records.') }}
            </p>
            <div class="mx-auto mt-6 inline-block rounded-lg border border-primary-200 bg-primary-50 px-6 py-3 text-2xl font-bold tracking-widest text-primary-700 dark:border-primary-400/30 dark:bg-primary-400/10 dark:text-primary-300">
                {{ $referenceNumber }}
            </div>
        </div>
    @elseif ($inactive)
        <div class="{{ $card }} text-center">
            <h1 class="text-xl font-semibold">{{ __('This intake link is no longer active') }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Please contact the agency directly to submit a referral.') }}
            </p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('New referral') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Submitting on behalf of :source', ['source' => $sourceName]) }}
                </p>
            </div>

            {{-- Patient --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Patient') }}</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('First name') }} *</label>
                        <input type="text" wire:model="data.first_name" class="{{ $input }}">
                        @error('data.first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Last name') }} *</label>
                        <input type="text" wire:model="data.last_name" class="{{ $input }}">
                        @error('data.last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Date of birth') }}</label>
                        <input type="date" wire:model="data.date_of_birth" class="{{ $input }}">
                        @error('data.date_of_birth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Gender') }}</label>
                        <select wire:model="data.gender" class="{{ $input }}">
                            <option value="">{{ __('Select') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="male">{{ __('Male') }}</option>
                            <option value="non_binary">{{ __('Non-binary') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Email') }}</label>
                        <input type="email" wire:model="data.email" class="{{ $input }}">
                        @error('data.email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Phone') }}</label>
                        <input type="text" wire:model="data.phone" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Alternate phone') }}</label>
                        <input type="text" wire:model="data.phone_alt" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Preferred language') }}</label>
                        <input type="text" wire:model="data.preferred_language" class="{{ $input }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">{{ __('Alt. contact name') }}</label>
                        <input type="text" wire:model="data.alt_contact_name" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Alt. contact phone') }}</label>
                        <input type="text" wire:model="data.alt_contact_phone" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Relationship') }}</label>
                        <input type="text" wire:model="data.alt_contact_relationship" class="{{ $input }}">
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Address') }}</h2>
                <div>
                    <label class="{{ $label }}">{{ __('Street address') }} *</label>
                    <input type="text" wire:model.blur="data.address" class="{{ $input }}">
                    @error('data.address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">{{ __('Address line 2') }}</label>
                    <input type="text" wire:model="data.address_2" class="{{ $input }}">
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">{{ __('City') }} *</label>
                        <input type="text" wire:model.blur="data.city" class="{{ $input }}">
                        @error('data.city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('State') }} *</label>
                        <input type="text" wire:model.blur="data.state" class="{{ $input }}">
                        @error('data.state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('ZIP') }}</label>
                        <input type="text" wire:model.blur="data.zip" class="{{ $input }}">
                    </div>
                </div>

                @if (($data['geocode_failed'] ?? false) === true)
                    <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-400">
                        {{ __("We couldn't confirm this address on the map. Drag the pin to the correct location.") }}
                    </div>
                @endif

                @include('filament.forms.leaflet-map', [
                    'mode' => 'marker',
                    'latModel' => 'data.latitude',
                    'lngModel' => 'data.longitude',
                    'failedModel' => 'data.geocode_failed',
                ])
            </div>

            {{-- Insurance --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Insurance') }}</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">{{ __('Insurance type') }}</label>
                        <select wire:model="data.insurance_type_id" class="{{ $input }}">
                            <option value="">{{ __('Select') }}</option>
                            @foreach ($this->insuranceTypeOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Member ID') }}</label>
                        <input type="text" wire:model="data.insurance_id" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Group') }}</label>
                        <input type="text" wire:model="data.insurance_group" class="{{ $input }}">
                    </div>
                </div>
            </div>

            {{-- Clinical & preferences --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Clinical & preferences') }}</h2>
                <div>
                    <label class="{{ $label }}">{{ __('Diagnosis') }}</label>
                    <textarea wire:model="data.diagnosis" rows="2" class="{{ $input }}"></textarea>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('PCP name') }}</label>
                        <input type="text" wire:model="data.pcp_name" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('PCP phone') }}</label>
                        <input type="text" wire:model="data.pcp_phone" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Preferred provider gender') }}</label>
                        <select wire:model="data.provider_gender_preference" class="{{ $input }}">
                            <option value="">{{ __('No preference') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="male">{{ __('Male') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Preferred provider language') }}</label>
                        <input type="text" wire:model="data.language_preference" class="{{ $input }}">
                    </div>
                </div>
            </div>

            {{-- Request --}}
            <div class="{{ $card }} space-y-4">
                <h2 class="text-base font-semibold">{{ __('Request details') }}</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('Discipline') }} *</label>
                        <select wire:model="data.discipline_id" class="{{ $input }}">
                            <option value="">{{ __('Select') }}</option>
                            @foreach ($this->disciplineOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('data.discipline_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Visit type') }}</label>
                        <input type="text" wire:model="data.visit_type" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Frequency') }}</label>
                        <input type="text" wire:model="data.frequency" placeholder="e.g. 2x/week" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Authorization number') }}</label>
                        <input type="text" wire:model="data.authorization_number" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Start date') }}</label>
                        <input type="date" wire:model="data.start_date" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('End date') }}</label>
                        <input type="date" wire:model="data.end_date" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Visits authorized') }}</label>
                        <input type="number" min="0" wire:model="data.visits_authorized" class="{{ $input }}">
                        @error('data.visits_authorized') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Travel radius (miles)') }}</label>
                        <input type="number" min="0" wire:model="data.radius_miles" class="{{ $input }}">
                        @error('data.radius_miles') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="{{ $label }}">{{ __('Notes') }}</label>
                    <textarea wire:model="data.notes" rows="3" class="{{ $input }}"></textarea>
                </div>
            </div>

            @include('livewire.auth.partials.recaptcha')

            <div class="flex items-center justify-end gap-3">
                <span wire:loading class="text-sm text-gray-500">{{ __('Submitting…') }}</span>
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                    {{ __('Submit referral') }}
                </button>
            </div>
        </form>
    @endif
</div>
