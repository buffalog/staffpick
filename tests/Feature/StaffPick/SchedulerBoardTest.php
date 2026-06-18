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

        $pending = $this->intake('pending');
        $active = $this->intake('active');
        $cancelled = $this->intake('cancelled');
        $noClinicians = $this->intake('no_clinicians_available');

        $board = Livewire::test(SchedulerBoard::class)->instance()->getBoard();

        $this->assertTrue($board['pending']->contains('id', $pending->id));
        $this->assertFalse($board['pending']->contains('id', $active->id));
        $this->assertTrue($board['active']->contains('id', $active->id));

        // Excluded statuses never appear in any board column.
        $allBoardIds = collect($board)->flatten()->pluck('id');
        $this->assertFalse($allBoardIds->contains($cancelled->id));
        $this->assertFalse($allBoardIds->contains($noClinicians->id));
    }

    public function test_only_statuses_with_a_valid_transition_are_draggable(): void
    {
        $board = new SchedulerBoard;

        foreach (['pending', 'on_hold', 'offered', 'assigned_pending', 'active'] as $status) {
            $this->assertTrue($board->isDraggableStatus($status), "{$status} should be draggable");
        }

        // Terminal / engine-managed — no manual move out.
        foreach (['matching', 'completed'] as $status) {
            $this->assertFalse($board->isDraggableStatus($status), "{$status} should not be draggable");
        }
    }

    public function test_engine_managed_cards_render_without_a_drag_handle(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $matching = $this->intake('matching');
        $active = $this->intake('active');

        Livewire::test(SchedulerBoard::class)
            ->assertSeeHtml('data-intake-id="'.$active->id.'"')
            ->assertDontSeeHtml('data-intake-id="'.$matching->id.'"');
    }

    public function test_a_valid_transition_updates_the_status(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('assigned_pending');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'assigned_pending', 'active')
            ->assertHasNoErrors();

        $this->assertSame('active', $intake->fresh()->status);
    }

    public function test_active_to_completed_sets_closed_at(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('active');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'active', 'completed');

        $fresh = $intake->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
    }

    public function test_an_engine_only_transition_is_rejected_and_status_is_unchanged(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('pending');

        // pending -> offered is engine-only.
        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'pending', 'offered')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__('Offers are dispatched automatically by the matching engine.'));

        $this->assertSame('pending', $intake->fresh()->status);
    }

    public function test_dragging_to_matching_explains_how_to_start_matching(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('pending');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'pending', 'matching')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__("Run 'Find Matches' from the Intake Request to start matching."));

        $this->assertSame('pending', $intake->fresh()->status);
    }

    public function test_a_backwards_transition_is_rejected(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('active');

        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'active', 'pending')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__("Cases can't move backwards. Use On Hold to pause a case instead."));

        $this->assertSame('active', $intake->fresh()->status);
    }

    public function test_other_blocked_transitions_point_the_scheduler_to_the_case(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $intake = $this->intake('pending');

        // pending -> assigned_pending is forward but engine-managed (not matching/offered).
        Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'pending', 'assigned_pending')
            ->assertDispatched('board-move-rejected')
            ->assertNotified(__('This transition happens automatically. Open the case to take action.'));

        $this->assertSame('pending', $intake->fresh()->status);
    }

    public function test_moving_to_on_hold_requires_a_reason_via_the_mounted_action(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));
        $reason = OnHoldReason::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Awaiting Authorization',
            'is_active' => true,
        ]);
        $intake = $this->intake('pending');

        $component = Livewire::test(SchedulerBoard::class)
            ->call('handleDrop', $intake->id, 'pending', 'on_hold')
            ->assertActionMounted('hold');

        // The status is not changed until the reason is supplied.
        $this->assertSame('pending', $intake->fresh()->status);

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
            ->call('handleDrop', $intake->id, 'on_hold', 'pending');

        $fresh = $intake->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->on_hold_reason_id);
    }

    public function test_needs_attention_lists_no_clinicians_and_cancelled(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $noClinicians = $this->intake('no_clinicians_available');
        $cancelled = $this->intake('cancelled');
        $pending = $this->intake('pending');

        $needs = Livewire::test(SchedulerBoard::class)->instance()->getNeedsAttention();

        $this->assertTrue($needs['no_clinicians_available']->contains('id', $noClinicians->id));
        $this->assertTrue($needs['cancelled']->contains('id', $cancelled->id));
        $this->assertFalse($needs['no_clinicians_available']->contains('id', $pending->id));
        $this->assertFalse($needs['cancelled']->contains('id', $pending->id));
    }
}
