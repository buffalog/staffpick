<?php

namespace App\Filament\Dashboard\Widgets;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Guava\Calendar\Contracts\HasCalendar;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\CalendarResource;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service Calendar — a provider-row timeline (Gantt) of active cases with a scheduled
 * evaluation date. Each provider (lead clinician) is a resource row; each active case
 * is an all-day bar on its evaluation_date. Clicking a case opens a read-only summary
 * modal with a link to the full case. Replaces the dispatch board.
 *
 * Built on guava/calendar (vkurko EventCalendar) — its resource timeline views are free,
 * no FullCalendar Premium license. Tenant-scoped and gated to admin/staff (PHI), same as
 * the IntakeRequest resource.
 */
class ServiceCalendarWidget extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::ResourceTimelineMonth;

    protected bool $eventClickEnabled = true;

    /**
     * vkurko toolbar: prev/next/today on the left, title centered, month/week toggle right.
     *
     * @var array<string, mixed>
     */
    protected array $options = [
        'headerToolbar' => [
            'start' => 'prev,next today',
            'center' => 'title',
            'end' => 'resourceTimelineMonth,resourceTimelineWeek',
        ],
    ];

    /** Distinct, stable colors so each provider row reads as its own lane. */
    private const PALETTE = [
        '#2563eb', '#16a34a', '#db2777', '#d97706',
        '#7c3aed', '#0891b2', '#dc2626', '#4f46e5',
    ];

    public static function canView(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    /**
     * Active cases with a scheduled evaluation date and an assigned lead clinician,
     * within the visible window. Returned as CalendarEvent objects so no Eventable
     * interface is needed on the model.
     *
     * @return array<int, CalendarEvent>
     */
    protected function getEvents(FetchInfo $info): array
    {
        return $this->activeScheduledCases()
            ->whereBetween('evaluation_date', [$info->start, $info->end])
            ->with(['subject', 'leadClinician'])
            ->get()
            ->map(fn (IntakeRequest $case): CalendarEvent => CalendarEvent::make($case)
                ->title($this->caseTitle($case))
                ->start($case->evaluation_date)
                ->end($case->evaluation_date)
                ->allDay()
                ->resourceId((string) $case->lead_clinician_id)
                ->backgroundColor($this->colorForProvider((int) $case->lead_clinician_id))
                ->textColor('#ffffff')
                ->action('viewCase'))
            ->all();
    }

    /**
     * One row per provider who has at least one active, scheduled case — no empty lanes.
     *
     * @return array<int, CalendarResource>
     */
    public function getResources(): array
    {
        $providerIds = $this->activeScheduledCases()
            ->whereNotNull('lead_clinician_id')
            ->distinct()
            ->pluck('lead_clinician_id')
            ->all();

        if ($providerIds === []) {
            return [];
        }

        return Provider::query()
            ->whereIn('id', $providerIds)
            ->orderBy('last_name')
            ->get()
            ->map(fn (Provider $provider): CalendarResource => CalendarResource::make((string) $provider->id)
                ->title(trim("{$provider->first_name} {$provider->last_name}") ?: __('Provider'))
                ->eventBackgroundColor($this->colorForProvider((int) $provider->id)))
            ->all();
    }

    /**
     * Read-only case summary on event click, with a button through to the full case.
     * Record is resolved from the clicked event via the calendar livewire.
     */
    public function viewCaseAction(): Action
    {
        return Action::make('viewCase')
            ->record(fn (HasCalendar $livewire): ?IntakeRequest => $livewire->getEventRecord())
            ->modalHeading(fn (IntakeRequest $record): string => $this->caseTitle($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->infolist([
                TextEntry::make('reference_number')->label(__('Reference'))->placeholder('—'),
                TextEntry::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => IntakeRequestResource::statusOptions()[$state] ?? str($state)->headline())
                    ->color(fn (string $state): string => IntakeRequestResource::statusColor($state)),
                TextEntry::make('provider_name')
                    ->label(__('Assigned Provider'))
                    ->state(fn (IntakeRequest $record): string => trim("{$record->leadClinician?->first_name} {$record->leadClinician?->last_name}"))
                    ->placeholder('—'),
                TextEntry::make('evaluation_date')->label(__('Service Date'))->date()->placeholder('—'),
            ])
            ->extraModalFooterActions([
                Action::make('openCase')
                    ->label(__('Open full case'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (HasCalendar $livewire): ?string => ($record = $livewire->getEventRecord()) instanceof IntakeRequest
                        ? IntakeRequestResource::getUrl('view', ['record' => $record])
                        : null),
            ]);
    }

    /** Active + scheduled, tenant-scoped — the shared base query for events and resources. */
    private function activeScheduledCases(): Builder
    {
        return IntakeRequest::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->where('status', 'active')
            ->whereNotNull('evaluation_date')
            ->whereNotNull('lead_clinician_id');
    }

    private function caseTitle(IntakeRequest $case): string
    {
        return trim("{$case->subject?->first_name} {$case->subject?->last_name}")
            ?: ($case->reference_number ?? __('Case'));
    }

    private function colorForProvider(int $providerId): string
    {
        return self::PALETTE[$providerId % count(self::PALETTE)];
    }
}
