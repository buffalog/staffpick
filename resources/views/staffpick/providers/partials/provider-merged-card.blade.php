@php
    /** @var \App\Models\StaffPick\Provider $provider */
    $provider = $record;
    $palette = \App\Filament\Dashboard\Support\DisciplinePalette::forAbbreviation($provider->discipline?->abbreviation);
    $disciplines = $provider->disciplines
        ->sortByDesc(fn ($discipline): bool => (bool) $discipline->pivot?->is_primary)
        ->values();

    $address = collect([$provider->address, $provider->city, $provider->state, $provider->zip])
        ->filter()->implode(', ');

    // Tri-state status. Derived from is_active + deactivated_at (NOT the 5-value status
    // enum): deactivated_at set = a hard removal with a reason logged; is_active = live;
    // otherwise a pause.
    $status = filled($provider->deactivated_at)
        ? ['label' => __('Deactivated'), 'cls' => 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400']
        : ($provider->is_active
            ? ['label' => __('Active'), 'cls' => 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400']
            : ['label' => __('Inactive'), 'cls' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400']);

    $notSet = '<span class="text-gray-400 dark:text-gray-500">'.__('Not set').'</span>';
    $field = fn (?string $value): string => filled($value) ? e($value) : $notSet;
@endphp

<div class="overflow-hidden rounded-xl border-2 bg-white shadow-sm dark:bg-gray-900" style="border-color: {{ $palette['text'] }};">
    {{-- Colored name band = the header of this card (name left, chips + tier right),
         flush to the top edge; the bg change to the white body reads as the divider. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" style="background-color: {{ $palette['bg'] }}; color: {{ $palette['text'] }};">
        <div class="text-2xl font-bold">{{ $provider->full_name }}</div>
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($disciplines as $discipline)
                @include('staffpick.providers.partials.discipline-chip', ['abbreviation' => $discipline->abbreviation, 'name' => $discipline->name])
            @endforeach
            @include('staffpick.providers.partials.tier-badge', ['tier' => $provider->tier])
        </div>
    </div>

    <div class="p-5">
        {{-- Address (one compact line) + status pill opposite --}}
        <div class="flex items-start justify-between gap-4">
            <div class="text-sm text-gray-700 dark:text-gray-200">{!! $address !== '' ? e($address) : $notSet !!}</div>
            <span class="inline-flex shrink-0 items-center rounded-full text-xs font-medium ring-1 ring-inset {{ $status['cls'] }}" style="padding: 5px 12px; line-height: 1;">
                {{-- Dot styled inline (h-1.5/w-1.5/bg-current aren't in Filament's CSS build);
                     background: currentColor = the pill's darker same-hue text color. --}}
                <span style="width: 6px; height: 6px; border-radius: 9999px; background: currentColor; margin-right: 8px; flex: none;"></span>
                {{ $status['label'] }}
            </span>
        </div>

        {{-- Left: Email / Phone. Right: Business Name / Alternate Phone. --}}
        <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
            <div class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Email') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($provider->email) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Phone') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($provider->phone) !!}</dd>
                </div>
            </div>
            <div class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Business Name') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($provider->business_name) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Alternate Phone') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($provider->phone_alt) !!}</dd>
                </div>
            </div>
        </div>
    </div>

    {{-- Payroll footer — de-emphasized (usually unset). --}}
    <div class="border-t border-gray-100 px-5 py-2.5 text-xs text-gray-400 dark:border-white/5 dark:text-gray-500">
        <span class="font-medium">{{ __('Payroll') }}</span>
        · {{ __('Payroll ID') }}: {{ filled($provider->payroll_id) ? $provider->payroll_id : __('Not set') }}
        · {{ __('Tax ID') }}: {{ filled($provider->tax_id) ? $provider->tax_id : __('Not set') }}
    </div>
</div>
