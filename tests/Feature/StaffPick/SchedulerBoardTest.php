<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\SchedulerBoard;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\OnHoldReason;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class SchedulerBoardTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function intake(string $status): IntakeRequest
    {
        return IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => $status,
        ]);
    }

    public function test_a_tenant_admin_can_access_the_board(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $this->assertTrue(SchedulerBoard::canAccess());

        Livewire::test(SchedulerBoard::class)->assertSuccessful();
    }

    public function test_a_non_admin_cannot_access_the_board(): void
    {
        $this->actingAs($this->createUser($this->tenant));

        $this->assertFalse(SchedulerBoard::canAccess());
    }

    public function test_cards_appear_in_their_status_columns_and_excluded_statuses_are_absent(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $unmatched = $this->intake('unmatched');
        $matched = $this->intake('matched');
        $cancelled = $this->intake('cancelled');
        $escalated = $this->intake('escalated');

        $board = Livewire::test(SchedulerBoard::class)->instance()->getBoard();

        $this->assertTrue($board['unmatched']->contains('id', $unmatched->id));
        $this->assertFalse($board['unmatched']->contains('id', $matched->id));
        $this->assertTrue($board['matched']->contains('id', $matched->id));

        // Cancelled / escalated live in Needs Attention, never in a board column.
        $allBoardIds = collect($board)->flatten()->pluck('id');
        $this->assertFalse($allBoardIds->contains($cancelled->id));
        $this->assertFalse($allBoardIds->contains($escalated->id));
    }

    public function test_a_partial_staffing_card_shows_a_partial_badge(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $this->intake('unmatched')->update(['is_partial_staffing' => true]);

        Livewire::test(SchedulerBoard::class)
            ->assertSee('Partial');
    }

    public function test_only_statuses_with_a_valid_transition_are_draggable(): void
    {
        $board = new SchedulerBoard;

        foreach (['unmatched', 'match_sent', 'matched', 'on_hold'] as $status) {
            $this->assertTrue($board->isDraggableStatus($status), "{$status} should be draggable");
        }

        // Terminal — no scheduler-owned move out of Completed.
        $this->assertFalse($board->isDraggableStatus('completed'), 'completed should not be draggable');
    }

    public function test_terminal_cards_render_without_a_drag_handle(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $completed = $this->intake('completed');
        $matched = $this->intake('matched');

        Livewire::test(SchedulerBoard::class)
            ->assertSeeHtml('data-intake-id="'.$matched->id.'"')
            ->assertDontSeeHtml('data-intake-id="'.$completed->id.'"');
    }

    public function test_a_valid_transition_updates_the_status(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('matched');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'matched', 'completed')
            ->assertHasNoErrors();

        $this->assertSame('completed', $intake->fresh()->status);
    }

    public function test_matched_to_completed_sets_closed_at(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('matched');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'matched', 'completed');

        $fresh = $intake->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
    }

    public function test_dispatching_an_offer_by_hand_is_rejected(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('unmatched');

        // unmatched -> match_sent is engine-only.
        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'unmatched', 'match_sent')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__('Offers are dispatched automatically by the matching engine.'));

        $this->assertSame('unmatched', $intake->fresh()->status);
    }

    public function test_dragging_to_matched_explains_that_acceptance_drives_it(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('unmatched');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'unmatched', 'matched')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__('Cases become Matched automatically when a provider accepts an offer.'));

        $this->assertSame('unmatched', $intake->fresh()->status);
    }

    public function test_a_backwards_transition_is_rejected(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('matched');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'matched', 'unmatched')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__("Cases can't move backwards. Use On Hold to pause a case instead."));

        $this->assertSame('matched', $intake->fresh()->status);
    }

    public function test_other_blocked_transitions_point_the_scheduler_to_the_case(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('unmatched');

        // unmatched -> completed is forward, but skips the pipeline and isn't scheduler-owned.
        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'unmatched', 'completed')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__('This transition happens automatically. Open the case to take action.'));

        $this->assertSame('unmatched', $intake->fresh()->status);
    }

    public function test_moving_to_on_hold_requires_a_reason_via_the_mounted_action(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $reason = OnHoldReason::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Awaiting Authorization',
            'is_active' => true,
        ]);
        $intake = $this->intake('unmatched');

        $component = Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'unmatched', 'on_hold')
            ->assertActionMounted('hold');

        // The status is not changed until the reason is supplied.
        $this->assertSame('unmatched', $intake->fresh()->status);

        $component
            ->setActionData(['on_hold_reason_id' => $reason->id, 'status_notes' => 'Need auth #'])
            ->callMountedAction()
            ->assertHasNoErrors();

        $fresh = $intake->fresh();
        $this->assertSame('on_hold', $fresh->status);
        $this->assertSame($reason->id, (int) $fresh->on_hold_reason_id);
    }

    public function test_resuming_from_hold_clears_the_hold_reason(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $reason = OnHoldReason::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Patient Unavailable',
            'is_active' => true,
        ]);
        $intake = $this->intake('on_hold');
        $intake->update(['on_hold_reason_id' => $reason->id]);

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'on_hold', 'unmatched');

        $fresh = $intake->fresh();
        $this->assertSame('unmatched', $fresh->status);
        $this->assertNull($fresh->on_hold_reason_id);
    }

    public function test_needs_attention_lists_escalated_and_cancelled(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $escalated = $this->intake('escalated');
        $cancelled = $this->intake('cancelled');
        $unmatched = $this->intake('unmatched');

        $needs = Livewire::test(SchedulerBoard::class)->instance()->getNeedsAttention();

        $this->assertTrue($needs['escalated']->contains('id', $escalated->id));
        $this->assertTrue($needs['cancelled']->contains('id', $cancelled->id));
        $this->assertFalse($needs['escalated']->contains('id', $unmatched->id));
        $this->assertFalse($needs['cancelled']->contains('id', $unmatched->id));
    }
}
