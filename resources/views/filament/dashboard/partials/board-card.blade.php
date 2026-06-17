@php
    /** @var \App\Models\StaffPick\IntakeRequest $card */
    $draggable = $draggable ?? false;
    $retrigger = $retrigger ?? false;

    $abbr = $card->discipline?->abbreviation;
    $disciplineBadge = [
        'PT' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
        'OT' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        'SLP' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    ][$abbr] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-300';

    $onHold = $card->status === 'on_hold';

    // HIPAA: first name + last initial only.
    $first = trim($card->subject?->first_name ?? '');
    $lastInitial = \Illuminate\Support\Str::substr($card->subject?->last_name ?? '', 0, 1);
    $patient = trim($first.' '.($lastInitial !== '' ? $lastInitial.'.' : ''));
    $patient = $patient !== '' ? $patient : __('Unknown patient');

    $days = $card->updated_at ? (int) round($card->updated_at->diffInDays(now())) : null;

    // Only board cards carry the count alias; needs-attention cards default to 0.
    $hasLanguageWarning = (int) ($card->language_warning_count ?? 0) > 0;

    $viewUrl = \App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource::getUrl('view', ['record' => $card->getKey()]);
@endphp

<div
    wire:key="board-card-{{ $card->getKey() }}"
    @if ($draggable) data-intake-id="{{ $card->getKey() }}" @endif
    class="relative rounded-lg border bg-white p-3 shadow-sm dark:bg-gray-900 {{ $onHold ? 'border-danger-400 ring-1 ring-danger-300 dark:border-danger-500/50' : 'border-gray-200 dark:border-white/10' }} {{ $draggable ? 'cursor-grab active:cursor-grabbing' : '' }}"
>
    {{-- Stretched link: clicking anywhere on the card opens the case (except interactive bits below). --}}
    <a href="{{ $viewUrl }}" class="absolute inset-0 z-0" aria-label="{{ __('Open case :ref', ['ref' => $card->reference_number]) }}"></a>

    <div class="pointer-events-none relative z-10 flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $patient }}</span>
            @if ($abbr)
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $disciplineBadge }}">{{ $abbr }}</span>
            @endif
        </div>

        <div class="truncate text-xs text-gray-500 dark:text-gray-400">
            {{ $card->referralSource?->name ?? __('No referral source') }}
        </div>

        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-mono text-gray-600 dark:text-gray-300">{{ $card->reference_number }}</span>
            @if ($days !== null)
                <span class="text-gray-400" title="{{ __('Days in current status') }}">{{ $days }}{{ __('d') }}</span>
            @endif
        </div>

        @if ($onHold || $hasLanguageWarning)
            <div class="flex flex-wrap items-center gap-1">
                @if ($onHold)
                    <span class="rounded bg-danger-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-danger-700 dark:bg-danger-500/20 dark:text-danger-300">{{ __('On hold') }}</span>
                @endif
                @if ($hasLanguageWarning)
                    <span class="inline-flex items-center rounded bg-warning-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning-700 dark:bg-warning-500/20 dark:text-warning-300" title="{{ __('A matched provider has a language mismatch with the patient') }}">{{ __('Language') }}</span>
                @endif
            </div>
        @endif

        @if ($retrigger)
            <div class="pointer-events-auto relative z-20 pt-1">
                <x-filament::button
                    size="xs"
                    color="warning"
                    icon="heroicon-o-arrow-path"
                    wire:click="mountAction('retrigger', { intakeId: {{ $card->getKey() }} })"
                >
                    {{ __('Re-trigger') }}
                </x-filament::button>
            </div>
        @endif
    </div>
</div>
