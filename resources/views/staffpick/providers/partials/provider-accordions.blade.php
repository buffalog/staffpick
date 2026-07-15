@php
    /** @var \App\Models\StaffPick\Provider $provider */
    $provider = $record;
    $disciplines = $provider->disciplines
        ->sortByDesc(fn ($discipline): bool => (bool) $discipline->pivot?->is_primary)
        ->values();
    $specialties = $provider->specialties->pluck('name')->filter();
    $languages = $provider->languages->pluck('name')->filter();
    $feedUrl = $provider->calendarFeedUrl();
    $notSet = '<span class="text-gray-400 dark:text-gray-500">'.__('Not set').'</span>';
    $field = fn (?string $value): string => filled($value) ? e($value) : $notSet;
@endphp

@include('staffpick.partials.accordion-styles')

<div class="fi-sp-accordions">
    <x-sp-accordion :title="__('Classification')">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Disciplines') }}</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-1.5">
                    @foreach ($disciplines as $discipline)
                        @include('staffpick.providers.partials.discipline-chip', ['abbreviation' => $discipline->abbreviation, 'name' => $discipline->name])
                    @endforeach
                    @include('staffpick.providers.partials.tier-badge', ['tier' => $provider->tier])
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Gender') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($provider->gender) !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Office') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $field($provider->office?->name) !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Is Contractor') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $provider->is_contractor ? __('Yes') : __('No') }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Specialties') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @forelse ($specialties as $specialty)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-950/10 dark:bg-white/10 dark:text-gray-200">{{ $specialty }}</span>
                    @empty
                        {!! $notSet !!}
                    @endforelse
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Languages Spoken') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @forelse ($languages as $language)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-950/10 dark:bg-white/10 dark:text-gray-200">{{ $language }}</span>
                    @empty
                        {!! $notSet !!}
                    @endforelse
                </dd>
            </div>
        </dl>
    </x-sp-accordion>

    <x-sp-accordion :title="__('Matching')">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Preferred Radius') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $provider->radius_preferred_miles }}{{ __(' mi') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Maximum Radius') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $provider->radius_max_miles }}{{ __(' mi') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Can Adjust Own Service Zones') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $provider->can_adjust_own_service_zones ? __('Yes') : __('No') }}</dd>
            </div>
        </dl>
    </x-sp-accordion>

    <x-sp-accordion :title="__('Calendar Feed')">
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Feed URL') }}</dt>
                <dd class="font-medium break-all text-gray-900 dark:text-white">{!! filled($feedUrl) ? e($feedUrl) : '<span class="text-gray-400 dark:text-gray-500">'.e(__('Not generated — the provider creates this from their profile.')).'</span>' !!}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Generated') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{!! $provider->calendar_token_generated_at ? e($provider->calendar_token_generated_at->format(config('app.datetime_format'))) : $notSet !!}</dd>
            </div>
        </dl>
    </x-sp-accordion>

    <x-sp-accordion :title="__('Notes')" :dot="filled($provider->notes) ? '#f59e0b' : false">
        <div class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-200">{!! filled($provider->notes) ? e($provider->notes) : '<span class="text-gray-400 dark:text-gray-500">'.e(__('No notes.')).'</span>' !!}</div>
    </x-sp-accordion>

    {{-- Credentials: the existing relation manager (table + Add + per-row Verify) embedded
         so it keeps all behavior, flattened by the CSS above to sit in the accordion body.
         Its own heading is dropped (this header replaces it); the red dot reflects the same
         expiry threshold. --}}
    <x-sp-accordion :title="__('Credentials')" :dot="$provider->credentialAlertCount() > 0 ? '#ef4444' : false">
        @livewire(
            \App\Filament\Dashboard\Resources\Providers\RelationManagers\CredentialsRelationManager::class,
            ['ownerRecord' => $provider, 'pageClass' => \App\Filament\Dashboard\Resources\Providers\Pages\ViewProvider::class],
            key('credentials-rm-'.$provider->id)
        )
    </x-sp-accordion>
</div>
