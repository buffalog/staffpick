<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use App\Jobs\StaffPick\DispatchOffers;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\OnHoldReason;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Scheduler Kanban board — the visual pipeline that replaces tab-filtered tables.
 * One column per workable status; cards drag between columns to drive the few
 * manual status transitions a scheduler owns. Engine-driven transitions (matching,
 * offered) and backwards moves are rejected server-side — the board is authoritative
 * and the drop is reverted by re-render. Gated to tenant admins (PHI), same as the
 * IntakeRequest resource.
 */
class SchedulerBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $slug = 'board';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.dashboard.pages.scheduler-board';

    /**
     * Board columns, left to right. Cancelled / no_clinicians_available are
     * deliberately excluded — they live in the "Needs Attention" section.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'pending' => 'Pending',
        'matching' => 'Matching',
        'offered' => 'Offered',
        'assigned_pending' => 'Assigned Pending',
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
    ];

    /**
     * Scheduler-owned transitions: from => [to => requiresHoldReason]. Anything not
     * listed is rejected (engine-only or a backwards move).
     *
     * @var array<string, array<string, bool>>
     */
    private const TRANSITIONS = [
        'pending' => ['on_hold' => true],
        'on_hold' => ['pending' => false],
        'offered' => ['on_hold' => true],
        'assigned_pending' => ['active' => false],
        'active' => ['completed' => false, 'on_hold' => true],
    ];

    /**
     * Linear case pipeline, used only to detect a backwards drag. On Hold is
     * deliberately excluded — it's an orthogonal pause state, not a pipeline stage,
     * so moving out of it (e.g. on_hold → active) isn't classified as "backwards".
     *
     * @var array<int, string>
     */
    private const PIPELINE = ['pending', 'matching', 'offered', 'assigned_pending', 'active', 'completed'];

    /** ISO-8601 timestamp of the last board build, for the "updated X seconds ago" indicator. */
    public ?string $lastUpdatedAt = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Board');
    }

    public static function getNavigationLabel(): string
    {
        return __('Board');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Dispatch');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant || ! auth()->check()) {
            return false;
        }

        // Super admins have full visibility into any tenant's dashboard via bypass.
        if (auth()->user()->isSuperAdmin()) {
            return true;
        }

        return in_array(
            TenancyPermissionConstants::ROLE_ADMIN,
            app(TenantPermissionService::class)->getTenantUserRoles($tenant, auth()->user()),
            true,
        );
    }

    /**
     * Cards grouped by board column, in column order. Each value is a Collection of
     * IntakeRequest rows eager-loaded for card rendering.
     *
     * @return array<string, Collection<int, IntakeRequest>>
     */
    public function getBoard(): array
    {
        $grouped = IntakeRequest::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->whereIn('status', array_keys(self::COLUMNS))
            ->with(['subject', 'referralSource', 'discipline'])
            // withCount (a scalar count subquery), not withExists — SQL Server rejects a
            // bare `exists(...)` in the SELECT list, so we count flagged offers and treat
            // any positive count as a warning.
            ->withCount(['assignmentOffers as language_warning_count' => fn (Builder $query) => $query->where('language_warning', true)])
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('status');

        $this->lastUpdatedAt = now()->toIso8601String();

        $board = [];

        foreach (array_keys(self::COLUMNS) as $status) {
            $board[$status] = $grouped->get($status, collect());
        }

        return $board;
    }

    /**
     * The two "Needs Attention" lists shown below the board.
     *
     * @return array{no_clinicians_available: Collection<int, IntakeRequest>, cancelled: Collection<int, IntakeRequest>}
     */
    public function getNeedsAttention(): array
    {
        $load = fn (string $status): Collection => IntakeRequest::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->where('status', $status)
            ->with(['subject', 'referralSource', 'discipline'])
            ->orderByDesc('updated_at')
            ->get();

        return [
            'no_clinicians_available' => $load('no_clinicians_available'),
            'cancelled' => $load('cancelled'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnLabels(): array
    {
        return array_map(fn (string $label): string => __($label), self::COLUMNS);
    }

    /**
     * Whether cards in this column can be dragged at all — true only when the status
     * has at least one scheduler-owned transition. Terminal/engine-managed columns
     * (matching, completed) have no manual move out, so their cards are fixed: showing
     * them as draggable just invites a guaranteed "requires system action" rejection.
     */
    public function isDraggableStatus(string $status): bool
    {
        return array_key_exists($status, self::TRANSITIONS);
    }

    /**
     * Aggregate counts for the board header chips and the right-hand stats panel,
     * derived from the already-loaded board/needs collections (no extra queries).
     *
     * @param  array<string, Collection<int, IntakeRequest>>  $board
     * @param  array{no_clinicians_available: Collection<int, IntakeRequest>, cancelled: Collection<int, IntakeRequest>}  $needs
     * @return array{total_active: int, offered: int, needs_attention: int, by_discipline: array<string, int>}
     */
    public function boardStats(array $board, array $needs): array
    {
        $byDiscipline = ['PT' => 0, 'OT' => 0, 'SLP' => 0];

        foreach ($board as $cards) {
            foreach ($cards as $card) {
                $abbr = $card->discipline?->abbreviation;

                if ($abbr !== null && array_key_exists($abbr, $byDiscipline)) {
                    $byDiscipline[$abbr]++;
                }
            }
        }

        return [
            'total_active' => $board['active']->count(),
            'offered' => $board['offered']->count(),
            'needs_attention' => $needs['no_clinicians_available']->count() + $needs['cancelled']->count(),
            'by_discipline' => $byDiscipline,
        ];
    }

    /**
     * Handle a card dropped from one column onto another. Validates the transition
     * against the current DB status (guarding a stale board) and either applies it,
     * mounts the hold-reason modal, or rejects it. The board re-renders after this
     * method, so a rejected/aborted move snaps the card back to its real column.
     */
    public function handleDrop(int $intakeId, string $fromStatus, string $toStatus): void
    {
        abort_unless(static::canAccess(), 403);

        if ($fromStatus === $toStatus) {
            return;
        }

        $intake = $this->findIntake($intakeId);

        if ($intake === null) {
            $this->rejectMove(__('That case could not be found.'));

            return;
        }

        // The board may be stale (poll lag, a concurrent engine move). Trust the DB.
        if ($intake->status !== $fromStatus) {
            $this->rejectMove(__('This case has already moved — the board has been refreshed.'));

            return;
        }

        $requiresReason = self::TRANSITIONS[$fromStatus][$toStatus] ?? null;

        if ($requiresReason === null) {
            $this->rejectMove($this->blockedTransitionMessage($fromStatus, $toStatus));

            return;
        }

        if ($requiresReason === true) {
            $this->mountAction('hold', ['intakeId' => $intakeId, 'fromStatus' => $fromStatus]);

            return;
        }

        $this->applyTransition($intake, $toStatus);

        Notification::make()
            ->title(__('Case moved to :status', ['status' => __(self::COLUMNS[$toStatus])]))
            ->success()
            ->send();
    }

    /**
     * The hold-reason modal, mounted when a card is dropped into "On Hold" from an
     * allowed column. Captures the required reason (+ optional notes) before applying.
     */
    public function holdAction(): Action
    {
        return Action::make('hold')
            ->label(__('Place on hold'))
            ->modalHeading(__('Place case on hold'))
            ->modalSubmitActionLabel(__('Place on hold'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('warning')
            ->schema([
                Select::make('on_hold_reason_id')
                    ->label(__('Reason'))
                    ->options(fn (): array => $this->onHoldReasonOptions())
                    ->required(),
                Textarea::make('status_notes')
                    ->label(__('Notes'))
                    ->maxLength(500),
            ])
            ->action(function (array $arguments, array $data): void {
                abort_unless(static::canAccess(), 403);

                $intake = $this->findIntake((int) ($arguments['intakeId'] ?? 0));
                $from = $arguments['fromStatus'] ?? null;

                if ($intake === null || $intake->status !== $from || ! (self::TRANSITIONS[$from]['on_hold'] ?? false)) {
                    $this->rejectMove(__('This case can no longer be placed on hold.'));

                    return;
                }

                $intake->update([
                    'status' => 'on_hold',
                    'on_hold_reason_id' => (int) $data['on_hold_reason_id'],
                    'status_notes' => $data['status_notes'] ?? null,
                ]);

                Notification::make()
                    ->title(__('Case placed on hold'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Re-trigger matching for an exhausted (no_clinicians_available) case with a
     * one-time expanded radius. Mounted from the Needs Attention section.
     */
    public function retriggerAction(): Action
    {
        return Action::make('retrigger')
            ->label(__('Re-trigger Matching'))
            ->modalHeading(__('Re-trigger matching'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->schema([
                TextInput::make('radius_override')
                    ->label(__('Expanded radius (miles)'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(500)
                    ->required()
                    ->helperText(__('One-time for this case only — does not change tenant defaults or provider preferences.')),
            ])
            ->action(function (array $arguments, array $data): void {
                abort_unless(static::canAccess(), 403);

                $intake = $this->findIntake((int) ($arguments['intakeId'] ?? 0));

                if ($intake === null || $intake->status !== 'no_clinicians_available') {
                    return;
                }

                DispatchOffers::dispatch($intake->id, (float) $data['radius_override']);

                Notification::make()
                    ->title(__('Matching re-triggered'))
                    ->body(__('Re-running with an expanded radius of :miles miles.', ['miles' => (int) $data['radius_override']]))
                    ->success()
                    ->send();
            });
    }

    private function applyTransition(IntakeRequest $intake, string $toStatus): void
    {
        $attributes = ['status' => $toStatus];

        if ($toStatus === 'completed') {
            $attributes['closed_at'] = now();
        }

        if ($toStatus === 'pending') {
            // Resuming from hold — clear the hold reason.
            $attributes['on_hold_reason_id'] = null;
        }

        $intake->update($attributes);
    }

    /**
     * Contextual guidance for a drag the board can't apply, so the scheduler knows
     * what to do instead of a generic "system action" rejection. Keyed off the
     * attempted from/to status.
     */
    private function blockedTransitionMessage(string $fromStatus, string $toStatus): string
    {
        if ($toStatus === 'matching') {
            return __("Run 'Find Matches' from the Intake Request to start matching.");
        }

        if ($toStatus === 'offered') {
            return __('Offers are dispatched automatically by the matching engine.');
        }

        $fromIndex = array_search($fromStatus, self::PIPELINE, true);
        $toIndex = array_search($toStatus, self::PIPELINE, true);

        if ($fromIndex !== false && $toIndex !== false && $toIndex < $fromIndex) {
            return __("Cases can't move backwards. Use On Hold to pause a case instead.");
        }

        return __('This transition happens automatically. Open the case to take action.');
    }

    private function rejectMove(string $message): void
    {
        // The card is reverted by the board re-render; the event lets the client
        // drop any optimistic state immediately, and the toast explains why.
        $this->dispatch('board-move-rejected');

        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }

    private function findIntake(int $id): ?IntakeRequest
    {
        if ($id <= 0) {
            return null;
        }

        return IntakeRequest::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->whereKey($id)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function onHoldReasonOptions(): array
    {
        return OnHoldReason::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [HelpHeaderAction::make('scheduler/dispatch-board')];
    }
}
