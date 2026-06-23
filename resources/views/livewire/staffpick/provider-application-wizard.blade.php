@php
    $input = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm';
    $label = 'block text-sm font-medium text-gray-700';
    $steps = [
        1 => __('Identity'),
        2 => __('Location'),
        3 => __('Classification'),
        4 => __('Specialties'),
        5 => __('Service Zones'),
        6 => __('Credentials'),
    ];
@endphp

<div>
    @if ($submitted)
        {{-- Confirmation screen --}}
        <div class="rounded-xl border border-green-200 bg-green-50 p-8 text-center">
            <x-filament::icon icon="heroicon-o-check-circle" class="mx-auto mb-3 h-12 w-12 text-green-500" />
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Application submitted') }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('Your application has been submitted. :tenant will review it and be in touch.', ['tenant' => $application->tenant?->name]) }}
            </p>
        </div>
    @else
        {{-- Stepper --}}
        <ol class="mb-6 flex flex-wrap gap-2 text-xs font-medium">
            @foreach ($steps as $num => $title)
                <li @class([
                    'flex items-center gap-1.5 rounded-full px-3 py-1',
                    'bg-indigo-600 text-white' => $num === $step,
                    'bg-indigo-100 text-indigo-700' => $num < $step,
                    'bg-gray-100 text-gray-500' => $num > $step,
                ])>
                    <span>{{ $num }}.</span> {{ $title }}
                </li>
            @endforeach
        </ol>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            {{-- Step 1: Identity --}}
            @if ($step === 1)
                <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('Your details') }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                        <label class="{{ $label }}">{{ __('Email') }} *</label>
                        <input type="email" wire:model="data.email" class="{{ $input }}">
                        @error('data.email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Phone') }}</label>
                        <input type="text" wire:model="data.phone" class="{{ $input }}">
                        @error('data.phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ __("We'll email you a link so you can finish later if you need to.") }}</p>
            @endif

            {{-- Step 2: Location --}}
            @if ($step === 2)
                <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('Where are you based?') }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">{{ __('Street address') }}</label>
                        <input type="text" wire:model.blur="data.street_address" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('City') }}</label>
                        <input type="text" wire:model.blur="data.city" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('State') }}</label>
                        <input type="text" wire:model.blur="data.state" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('ZIP') }}</label>
                        <input type="text" wire:model.blur="data.zip" class="{{ $input }}">
                    </div>
                    <div class="flex items-end">
                        <button type="button" wire:click="geocode" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                            {{ __('Find address on map') }}
                        </button>
                    </div>
                </div>
                @if ($data['geocode_failed'] ?? false)
                    <p class="mt-2 text-sm text-amber-600">{{ __("We couldn't confirm this address. Drag the pin to the correct location.") }}</p>
                @endif
                <div class="mt-4">
                    @include('filament.forms.leaflet-map', [
                        'mode' => 'marker',
                        'latModel' => 'data.latitude',
                        'lngModel' => 'data.longitude',
                        'failedModel' => 'data.geocode_failed',
                    ])
                </div>
            @endif

            {{-- Step 3: Discipline & Classification --}}
            @if ($step === 3)
                <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('Your practice') }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('Discipline') }} *</label>
                        <select wire:model.live="data.discipline_id" class="{{ $input }}">
                            <option value="">{{ __('Select') }}</option>
                            @foreach ($this->disciplineOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('data.discipline_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Gender') }}</label>
                        <select wire:model="data.gender" class="{{ $input }}">
                            <option value="">{{ __('Prefer not to say') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="male">{{ __('Male') }}</option>
                            <option value="nonbinary">{{ __('Non-binary') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Preferred radius (miles)') }}</label>
                        <input type="number" min="1" wire:model="data.preferred_radius" class="{{ $input }}">
                        @error('data.preferred_radius') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Maximum radius (miles)') }}</label>
                        <input type="number" min="1" wire:model="data.maximum_radius" class="{{ $input }}">
                        @error('data.maximum_radius') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="data.is_contractor" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ __('I am an independent contractor') }}
                        </label>
                    </div>
                </div>
            @endif

            {{-- Step 4: Specialties --}}
            @if ($step === 4)
                @php $specialtyOptions = $this->specialtyOptions(); @endphp
                <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ __('Specialties') }}</h2>
                <select multiple wire:model="data.specialty_ids" @disabled(empty($specialtyOptions))
                        class="{{ $input }} h-40 disabled:opacity-50">
                    @foreach ($specialtyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    {{ empty($specialtyOptions)
                        ? __('Select a discipline in the previous step to see its specialties.')
                        : __('Optional. Hold Cmd/Ctrl to select more than one.') }}
                </p>
            @endif

            {{-- Step 5: Service Zones --}}
            @if ($step === 5)
                <h2 class="mb-2 text-lg font-semibold text-gray-900">{{ __('Service area') }}</h2>
                <p class="mb-4 text-sm text-gray-600">{{ __('Optionally outline the area you cover. Draw a polygon on the map — we use it to refine matching beyond your radius.') }}</p>
                @include('filament.forms.leaflet-map', [
                    'mode' => 'polygon',
                    'pointsModel' => 'data.service_zones',
                ])
            @endif

            {{-- Step 6: Credentials & Submit --}}
            @if ($step === 6)
                <h2 class="mb-2 text-lg font-semibold text-gray-900">{{ __('Credentials') }}</h2>
                <p class="mb-4 text-sm text-gray-600">{{ __('Upload your credential documents (PDF or image, up to 10MB each).') }}</p>
                <div class="space-y-4">
                    @forelse ($this->credentialTypes() as $type)
                        <div class="rounded-lg border border-gray-200 p-3">
                            <label class="{{ $label }}">{{ $type->name }}</label>
                            <input type="file" wire:model="credentialFiles.{{ $type->id }}" class="mt-1 block w-full text-sm">
                            <div wire:loading wire:target="credentialFiles.{{ $type->id }}" class="mt-1 text-xs text-gray-500">{{ __('Uploading…') }}</div>
                            @error('credentialFiles.'.$type->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No credential documents are required at this time.') }}</p>
                    @endforelse
                </div>
            @endif

            {{-- Nav buttons --}}
            <div class="mt-6 flex items-center justify-between">
                <button type="button" wire:click="previousStep" @class(['rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100', 'invisible' => $step === 1])>
                    ← {{ __('Back') }}
                </button>

                @if ($step < 6)
                    <button type="button" wire:click="nextStep" wire:loading.attr="disabled"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                        {{ __('Continue') }} →
                    </button>
                @else
                    <button type="button" wire:click="submit" wire:loading.attr="disabled"
                            class="rounded-lg bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50">
                        {{ __('Submit Application') }}
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
