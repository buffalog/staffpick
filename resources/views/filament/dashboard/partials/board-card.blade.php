@php
    /** @var \App\Models\StaffPick\IntakeRequest $card */
    $draggable = $draggable ?? false;
    $retrigger = $retrigger ?? false;

    // Discipline colour-blocking: a top strip + matching pill. PT=blue, OT=teal, SLP=violet.
    $abbr = $card->discipline?->abbreviation;
    $disc = [
        'PT' => ['strip' => 'border-t-blue-500', 'pill' => 'bg-blue-100 text-blue-700'],
        'OT' => ['strip' => 'border-t-teal-500', 'pill' => 'bg-teal-100 text-teal-700'],
        'SLP' => ['strip' => 'border-t-violet-500', 'pill' => 'bg-violet-100 text-violet-700'],
    ][$abbr] ?? ['strip' => 'border-t-gray-300', 'pill' => 'bg-gray-100 text-gray-600'];

    $onHold = $card->status === 'on_hold';
    $partial = (bool) $card->is_partial_staffing;

    // HIPAA: first name + last initial only.
    $first = trim($card->subject?->first_name ?? '');
    $lastInitial = \Illuminate\Support\Str::substr($card->subject?->last_name ?? '', 0, 1);
    $patient = trim($first.' '.($lastInitial !== '' ? $lastInitial.'.' : ''));
    $patient = $patient !== '' ? $patient : __('Unknown patient');

    $days = $card->updated_at ? (int) round($card->updated_at->diffInDays(now())) : null;
    $daysColor = $days === null
        ? 'text-gray-400'
        : ($days < 3 ? 'text-green-600' : ($days <= 7 ? 'text-amber-600' : 'text-red-600'));

    // Only board cards carry the count alias; needs-attention cards default to 0.
    $hasLanguageWarning = (int) ($card->language_warning_count ?? 0) > 0;

    $viewUrl = \App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource::getUrl('view', ['record' => $card->getKey()]);

    // Assigned-clinician identity color: 4px left border + 25% tint. on_hold red takes
    // precedence (urgency signal); unassigned cards fall back to white.
    $providerColor = $card->leadClinician?->color;
    $useProviderColor = ! $onHold && filled($providerColor);
@endphp

<div
    wire:key="board-card-{{ $card->getKey() }}"
    @if ($draggable) data-intake-id="{{ $card->getKey() }}" @endif
    @class([
        'relative rounded-lg border-t-4 p-3 shadow-sm transition hover:shadow-md',
        $disc['strip'],
        'border-l-4 border-l-red-500 bg-red-50' => $onHold,
        'bg-white' => ! $onHold && ! $useProviderColor,
        'border-l-4' => $useProviderColor,
        'cursor-grab active:cursor-grabbing' => $draggable,
    ])
    @if ($useProviderColor)
        style="border-left-color: {{ $providerColor }}; background-color: {{ \App\Models\StaffPick\Provider::hexToRgba($providerColor, 0.25) }};"
    @endif
>
    {{-- Stretched link: clicking the card opens the case (interactive bits opt back in below). --}}
    <a href="{{ $viewUrl }}" class="absolute inset-0 z-0" aria-label="{{ __('Open case :ref', ['ref' => $card->reference_number]) }}"></a>

    <div class="pointer-events-none relative z-10 flex flex-col gap-2">
        {{-- Patient name + discipline pill --}}
        <div class="flex items-start justify-between gap-2">
            <span class="text-base font-bold leading-tight text-gray-900">{{ $patient }}</span>
            @if ($abbr)
                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $disc['pill'] }}">{{ $abbr }}</span>
            @endif
        </div>

        {{-- Referral source --}}
        <div class="flex items-center gap-1.5 text-xs text-gray-500">
            <x-filament::icon icon="heroicon-o-building-office-2" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
            <span class="truncate">{{ $card->referralSource?->name ?? __('No referral source') }}</span>
        </div>

        {{-- Reference chip + days in status --}}
        <div class="flex items-center justify-between gap-2">
            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600">{{ $card->reference_number }}</span>
            @if ($days !== null)
                <span class="flex items-center gap-1 text-xs font-medium {{ $daysColor }}" title="{{ __('Days in current status') }}">
                    <x-filament::icon icon="heroicon-o-clock" class="h-3.5 w-3.5" />
                    {{ $days }}{{ __('d') }}
                </span>
            @endif
        </div>

        {{-- Status badges --}}
        @if ($onHold || $partial || $hasLanguageWarning)
            <div class="flex flex-wrap items-center gap-2">
                @if ($onHold)
                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700">{{ __('On hold') }}</span>
                @endif
                @if ($partial)
                    <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700" title="{{ __('Partial staffing — assistant already placed; match a lead clinician only') }}">{{ __('Partial') }}</span>
                @endif
                @if ($hasLanguageWarning)
                    <span class="pointer-events-auto inline-flex items-center text-amber-500" title="{{ __('No language match') }}">
                        <x-filament::icon icon="heroicon-o-flag" class="h-4 w-4" />
                    </span>
                @endif
            </div>
        @endif

        @if ($retrigger)
            <div class="pointer-events-auto relative z-20 pt-1">
                <x-filament::button
                    size="xs"
                    color="danger"
                    icon="heroicon-o-bolt"
                    wire:click="mountAction('retrigger', { intakeId: {{ $card->getKey() }} })"
                >
                    {{ __('Force Match') }}
                </x-filament::button>
            </div>
        @endif
    </div>
</div>
