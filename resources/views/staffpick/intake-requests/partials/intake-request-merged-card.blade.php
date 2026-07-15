@php
    use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
    use App\Filament\Dashboard\Resources\Subjects\SubjectResource;
    use App\Filament\Dashboard\Support\DisciplinePalette;
    use App\Models\StaffPick\TenantConfig;

    /** @var \App\Models\StaffPick\IntakeRequest $record */
    $palette = DisciplinePalette::forAbbreviation($record->discipline?->abbreviation);

    $subjectName = $record->subject
        ? trim("{$record->subject->first_name} {$record->subject->last_name}")
        : '';
    // Title = patient name, linked to the subject record; falls back to the reference
    // number (or a placeholder) when the case has no subject on file.
    $title = filled($subjectName) ? $subjectName : ($record->reference_number ?: __('Unnamed case'));
    $titleUrl = $record->subject_id
        ? SubjectResource::getUrl('edit', ['record' => $record->subject_id])
        : null;

    // Pill classes keyed off the same statusColor() the badges use, so the hero pill
    // matches case colors everywhere else.
    $status = [
        'label' => IntakeRequestResource::statusOptions()[$record->status] ?? str($record->status)->headline(),
        'cls' => [
            'success' => 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400',
            'warning' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400',
            'danger' => 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400',
            'info' => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400',
            'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-white/10 dark:text-gray-300',
        ][IntakeRequestResource::statusColor($record->status)] ?? 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-white/10 dark:text-gray-300',
    ];

    $notSet = '<span class="text-gray-400 dark:text-gray-500">'.__('Not set').'</span>';
    $field = fn (?string $value): string => filled($value) ? e($value) : '<span class="text-gray-400 dark:text-gray-500">—</span>';
@endphp

<div class="overflow-hidden rounded-xl border-2 bg-white shadow-sm dark:bg-gray-900" style="border-color: {{ $palette['text'] }};">
    {{-- Colored band = card header (title left, discipline chip right), flush to the top;
         the bg change to the white body reads as the divider. Cases carry no tier. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" style="background-color: {{ $palette['bg'] }}; color: {{ $palette['text'] }};">
        <div class="flex items-center gap-3">
            <x-filament::icon icon="heroicon-o-user" class="h-9 w-9 shrink-0" />
            <div class="text-2xl font-bold">
                @if ($titleUrl)
                    <a href="{{ $titleUrl }}" class="hover:underline">{{ $title }}</a>
                @else
                    {{ $title }}
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($record->discipline)
                @include('staffpick.providers.partials.discipline-chip', ['abbreviation' => $record->discipline->abbreviation, 'name' => $record->discipline->name])
            @endif
        </div>
    </div>

    <div class="p-5">
        {{-- Ref (one compact line) + status pill opposite --}}
        <div class="flex items-start justify-between gap-4">
            <div class="text-sm text-gray-700 dark:text-gray-200">{{ __('Ref') }}: {{ $record->reference_number ?: '—' }}</div>
            <span class="inline-flex shrink-0 items-center rounded-full text-xs font-medium ring-1 ring-inset {{ $status['cls'] }}" style="padding: 5px 12px; line-height: 1;">
                <span style="width: 6px; height: 6px; border-radius: 9999px; background: currentColor; margin-right: 8px; flex: none;"></span>
                {{ $status['label'] }}
            </span>
        </div>

        {{-- Left: Referral. Right: Discipline / Office / Assigner. --}}
        <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
            <div class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Referral Source') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($record->referralSource?->name) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Referring clinician') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($record->referring_clinician_name) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Referring clinician phone') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($record->referring_clinician_phone) !!}</dd>
                </div>
            </div>
            <div class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ TenantConfig::entityLabel('discipline', __('Discipline')) }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($record->discipline?->name) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Office') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($record->office?->name) !!}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Assigner') }}</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{!! $field($record->assigner?->name) !!}</dd>
                </div>
            </div>
        </div>
    </div>

    {{-- External references footer — de-emphasized (usually unset). --}}
    <div class="border-t border-gray-100 px-5 py-2.5 text-xs text-gray-400 dark:border-white/5 dark:text-gray-500">
        <span class="font-medium">{{ __('External References') }}</span>
        · {{ __('EMR ID') }}: {{ filled($record->emr_id) ? $record->emr_id : '—' }}
        · {{ __('Slack Channel') }}: {{ filled($record->slack_channel_id) ? $record->slack_channel_id : '—' }}
    </div>
</div>
