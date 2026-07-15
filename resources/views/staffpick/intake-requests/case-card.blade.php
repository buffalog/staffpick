@php
    use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
    use App\Filament\Dashboard\Support\DisciplinePalette;

    /** @var \App\Models\StaffPick\IntakeRequest $record */
    $record = $getRecord();
    $palette = DisciplinePalette::forAbbreviation($record->discipline?->abbreviation);

    $subjectName = $record->subject
        ? trim("{$record->subject->first_name} {$record->subject->last_name}")
        : '';
    $title = filled($subjectName) ? $subjectName : ($record->reference_number ?: __('Unnamed case'));

    // Status pill, colored off the same statusColor() the badges use (matches PR F's hero).
    $statusLabel = IntakeRequestResource::statusOptions()[$record->status] ?? str($record->status)->headline();
    $statusCls = [
        'success' => 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400',
        'warning' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400',
        'danger' => 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400',
        'info' => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-white/10 dark:text-gray-300',
    ][IntakeRequestResource::statusColor($record->status)] ?? 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-white/10 dark:text-gray-300';

    $startDate = $record->start_date?->format(config('app.date_format'));
@endphp

<div class="fi-sp-case-card flex h-full flex-col overflow-hidden rounded-xl border-2 bg-white pb-4 shadow-sm dark:bg-gray-900"
     style="border-color: {{ $palette['text'] }};">
    {{-- Header: patient name, in a band tinted to the discipline. Filament wraps this first
         child in an <a class="fi-ta-record-content"> with 0 16px padding; -mx-4 cancels that
         horizontal inset so the band bleeds to the border, px-8 then re-insets the name 16px so
         it lines up with the px-4 body below (same trick as the provider profile card). --}}
    <div class="-mx-4 px-8 pt-3 pb-2" style="background-color: {{ $palette['bg'] }}; color: {{ $palette['text'] }};">
        <div class="truncate text-base font-semibold">{{ $title }}</div>
    </div>

    {{-- Case glyph centered in the same 140px slot the provider card uses for the photo.
         min-h (not h) holds the slot open — a plain h-[140px] collapses under a cascade rule.
         This icon is the swappable glyph. --}}
    <div class="flex min-h-[140px] items-center justify-center border-y-2 border-gray-100 dark:border-white/5">
        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-14 w-14" style="color: {{ $palette['text'] }};" />
    </div>

    {{-- Discipline chip (a case has one discipline) --}}
    <div class="flex flex-wrap items-center gap-1.5 px-4 pt-3">
        @if ($record->discipline)
            @include('staffpick.providers.partials.discipline-chip', ['abbreviation' => $record->discipline->abbreviation, 'name' => $record->discipline->name])
        @endif
    </div>

    {{-- Status pill on its own line, in the slot the provider card gives the tier badge --}}
    <div class="px-4 pt-2">
        <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-medium ring-1 ring-inset {{ $statusCls }}">
            {{ $statusLabel }}
        </span>
    </div>

    {{-- Stat rows --}}
    <dl class="mt-3 flex-1 space-y-1.5 px-4 text-sm">
        <div class="flex items-center justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Reference') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $record->reference_number ?: '—' }}</dd>
        </div>
        <div class="flex items-start justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Referral Source') }}</dt>
            <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $record->referralSource?->name ?: '—' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Start Date') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $startDate ?: '—' }}</dd>
        </div>
        <div class="flex items-start justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Assigner') }}</dt>
            <dd class="text-right font-medium text-gray-900 dark:text-white">{{ $record->assigner?->name ?: '—' }}</dd>
        </div>
    </dl>
</div>
