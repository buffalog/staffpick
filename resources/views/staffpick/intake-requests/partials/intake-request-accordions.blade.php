@php
    /** @var \App\Models\StaffPick\IntakeRequest $record */
    $assignment = $record->currentAssignment;
    $requestedProviderName = $record->requestedProvider
        ? trim("{$record->requestedProvider->first_name} {$record->requestedProvider->last_name}")
        : '';
    $leadName = $record->leadClinician
        ? trim("{$record->leadClinician->first_name} {$record->leadClinician->last_name}")
        : '';
    $specialties = $record->specialties->pluck('name')->filter();

    $notSet = '<span class="text-gray-400 dark:text-gray-500">'.__('Not set').'</span>';
    $dash = '<span class="text-gray-400 dark:text-gray-500">—</span>';
    $field = fn (?string $value): string => filled($value) ? e($value) : $dash;
@endphp

@include('staffpick.partials.accordion-styles')

<div class="fi-sp-accordions">
    @if ($assignment !== null)
        <x-sp-accordion :title="__('Assignment')">
            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Provider') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{!! $field($assignment->provider ? trim("{$assignment->provider->first_name} {$assignment->provider->last_name}") : null) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Assignment Status') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{!! filled($assignment->status) ? e(str($assignment->status)->headline()) : $dash !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Assigned At') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{!! $assignment->assigned_at ? e($assignment->assigned_at->format('M j, Y')) : $dash !!}</dd>
                </div>
            </dl>
        </x-sp-accordion>
    @endif

    <x-sp-accordion :title="__('Service')">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Authorization Number') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($record->authorization_number) !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Frequency') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($record->frequency) !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Start of Care date') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $record->start_date ? e($record->start_date->format('M j, Y')) : $dash !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('End Date') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $record->end_date ? e($record->end_date->format('M j, Y')) : $dash !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Visits Authorized') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($record->visits_authorized !== null ? (string) $record->visits_authorized : null) !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Visits Completed') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($record->visits_completed !== null ? (string) $record->visits_completed : null) !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Visit Type') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($record->visit_type) !!}</dd>
            </div>
        </dl>
    </x-sp-accordion>

    <x-sp-accordion :title="__('Matching & Flags')">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Gender Preference') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! filled($record->subject?->provider_gender_preference) ? e(str($record->subject->provider_gender_preference)->title()) : '<span class="text-gray-400 dark:text-gray-500">'.e(__('No preference')).'</span>' !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Language Preference') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! filled($record->subject?->language_preference) ? e($record->subject->language_preference) : '<span class="text-gray-400 dark:text-gray-500">'.e(__('No preference')).'</span>' !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Requested Provider') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! filled($requestedProviderName) ? e($requestedProviderName) : '<span class="text-gray-400 dark:text-gray-500">'.e(__('None')).'</span>' !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Radius') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $record->radius_miles !== null ? e($record->radius_miles.__(' mi')) : $dash !!}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Requested Specialties') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @forelse ($specialties as $specialty)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-950/10 dark:bg-white/10 dark:text-gray-200">{{ $specialty }}</span>
                    @empty
                        <span class="text-gray-400 dark:text-gray-500">{{ __('None') }}</span>
                    @endforelse
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Lead clinician') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field(filled($leadName) ? $leadName : null) !!}</dd>
            </div>
            @if ($record->is_partial_staffing)
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Assistant clinician (in-house)') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{!! $field($record->assistant_clinician_name) !!}</dd>
                </div>
            @endif
            @foreach ([
                __('Partial staffing') => (bool) $record->is_partial_staffing,
                __('Manual Assignment') => (bool) $record->manual_assignment,
                __('Needs EMR Transition') => (bool) $record->needs_emr_transition,
                __('Paperwork Complete') => (bool) $record->paperwork_complete,
            ] as $label => $on)
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5">
                        @if ($on)
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-green-500" />
                        @else
                            <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 text-red-500" />
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-sp-accordion>

    <x-sp-accordion :title="__('Notes')" :dot="filled($record->notes) ? '#f59e0b' : false">
        <div class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-200">{!! filled($record->notes) ? e($record->notes) : $notSet !!}</div>
    </x-sp-accordion>
</div>
