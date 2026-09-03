<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\SchedulerBoard;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\AllCases;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ListIntakeRequests;
use App\Filament\Dashboard\Resources\Subjects\Pages\ListSubjects;
use App\Models\StaffPick\AuditEvent;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * The 'listed' half of the HIPAA audit stream: what a staff member SAW, not just what they
 * opened or changed.
 *
 * Every test here drives the rendered surface, never the concern's method in isolation. Four of
 * the defects found in this codebase survived because a test called a method directly and never
 * exercised the page, so an assertion about LogsRecordList::logListedRecords() would prove
 * nothing about whether All Cases actually writes a row.
 */
class ListAuditLoggingTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);

        $this->actingAs($this->createTenantAdmin($this->tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);

        // The suite shares one database with no rollback, so start from a known audit baseline
        // rather than counting deltas against whatever ran before.
        AuditEvent::query()->delete();
    }

    private function case(string $last, array $attributes = []): IntakeRequest
    {
        $subject = Subject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Pat',
            'last_name' => $last,
        ]);

        return IntakeRequest::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ], $attributes));
    }

    /** @return array<int, AuditEvent> */
    private function listedEvents(): array
    {
        return AuditEvent::query()->where('action', 'listed')->orderBy('id')->get()->all();
    }

    // ---- (a) one event per render, not per record --------------------------------------

    public function test_rendering_a_list_of_patients_writes_exactly_one_listed_event(): void
    {
        $cases = collect(['Alpha', 'Bravo', 'Charlie', 'Delta'])->map(fn (string $n) => $this->case($n));

        Livewire::test(ListIntakeRequests::class)->assertOk();

        $events = $this->listedEvents();

        // The whole design decision, pinned. Four patients on screen is ONE disclosure event,
        // not four rows. If this ever reads 4, the table becomes unreadable during an incident
        // and grows four times faster for six years.
        $this->assertCount(1, $events, sprintf(
            'Expected exactly 1 listed event for a 4-record render, got %d.',
            count($events),
        ));

        $context = $events[0]->context;
        $this->assertSame(4, $context['count']);
        $this->assertEqualsCanonicalizing($cases->pluck('id')->all(), $context['ids']);
        $this->assertSame(ListIntakeRequests::class, $context['surface']);
    }

    public function test_the_card_view_also_writes_one_event_for_the_whole_grid(): void
    {
        $this->case('Echo');
        $this->case('Foxtrot');

        Livewire::test(AllCases::class)->assertOk();

        $events = $this->listedEvents();
        $this->assertCount(1, $events);
        $this->assertSame(2, $events[0]->context['count']);
        $this->assertSame(AllCases::class, $events[0]->context['surface']);
    }

    // ---- (b) the ids are what was on screen, not what the query could have returned -----

    public function test_the_logged_ids_exclude_records_the_tenant_scope_kept_off_screen(): void
    {
        $mine = $this->case('Mine');

        // Another tenant's patient, and one of ours in a status Pending Cases does not show.
        $otherTenant = $this->createTenant();
        $otherDiscipline = Discipline::create(['tenant_id' => $otherTenant->id, 'name' => 'PT']);
        $theirs = IntakeRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
            'subject_id' => Subject::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'discipline_id' => $otherDiscipline->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);
        $wrongStatus = $this->case('Discharged', ['status' => IntakeRequest::STATUS_COMPLETED]);

        Livewire::test(ListIntakeRequests::class)->assertOk();

        $context = $this->listedEvents()[0]->context;

        $this->assertContains($mine->id, $context['ids']);

        // An audit row claiming a disclosure that did not happen is as wrong as a missing one.
        $this->assertNotContains($theirs->id, $context['ids'], 'Logged another tenant\'s record as disclosed.');
        $this->assertNotContains($wrongStatus->id, $context['ids'], 'Logged a record the status filter kept off screen.');
        $this->assertSame(1, $context['count']);
    }

    public function test_the_search_that_produced_the_list_is_recorded(): void
    {
        $this->case('Pihl');
        $this->case('Nguyen');
        $this->case('Okafor');

        Livewire::test(ListIntakeRequests::class)
            ->searchTable('Pihl')
            ->assertOk();

        // "Searched for 'Pihl' and saw 1 result" is the sentence an incident review needs.
        $searched = collect($this->listedEvents())
            ->first(fn (AuditEvent $e): bool => ($e->context['search'] ?? null) === 'Pihl');

        $this->assertNotNull($searched, 'No listed event recorded the search term that produced the list.');
        $this->assertSame(1, $searched->context['count']);
    }

    // ---- (c) pagination ----------------------------------------------------------------

    public function test_each_page_of_a_paginated_list_is_its_own_disclosure(): void
    {
        // Default page size is 10; 12 records gives a second page with a different set.
        for ($i = 1; $i <= 12; $i++) {
            $this->case('Patient'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        Livewire::test(ListIntakeRequests::class)
            ->assertOk()
            ->call('gotoPage', 2)
            ->assertOk();

        $events = $this->listedEvents();

        $this->assertCount(2, $events, 'Page 2 is a separate disclosure and needs its own event.');

        $pageOne = $events[0]->context;
        $pageTwo = $events[1]->context;

        $this->assertSame(1, $pageOne['page']);
        $this->assertSame(2, $pageTwo['page']);
        $this->assertSame(10, $pageOne['count']);
        $this->assertSame(2, $pageTwo['count']);
        $this->assertSame([], array_intersect($pageOne['ids'], $pageTwo['ids']), 'The two pages logged overlapping ids.');
    }

    // ---- (d) empty result set ----------------------------------------------------------

    public function test_an_empty_list_writes_nothing(): void
    {
        Livewire::test(ListIntakeRequests::class)->assertOk();

        $this->assertSame([], $this->listedEvents(), 'Nothing was disclosed, so nothing should be recorded.');
    }

    public function test_a_search_matching_nothing_writes_nothing(): void
    {
        $this->case('Present');

        Livewire::test(ListIntakeRequests::class)
            ->searchTable('ZzzNoSuchPatient')
            ->assertOk();

        $matched = collect($this->listedEvents())
            ->filter(fn (AuditEvent $e): bool => ($e->context['count'] ?? 0) === 0);

        $this->assertCount(0, $matched, 'An empty result set produced an event.');
    }

    // ---- (e) the id cap ----------------------------------------------------------------

    public function test_a_list_larger_than_the_cap_records_the_count_and_a_truncation_marker(): void
    {
        // PHP only exposes a trait constant through a using class, so read it off the surface
        // under test rather than duplicating the number here.
        $cap = SchedulerBoard::LISTED_ID_CAP;
        $total = $cap + 1;

        // SchedulerBoard is unpaginated: it ->get()s every case in the tenant, which is exactly
        // the surface the cap exists to bound. Three shared subjects keeps the fixture cheap
        // while still putting $total records on screen.
        $subjectIds = collect(range(1, 3))
            ->map(fn (int $i): int => Subject::factory()->create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'Cap',
                'last_name' => 'Patient'.$i,
            ])->id)
            ->all();

        for ($i = 0; $i < $total; $i++) {
            IntakeRequest::factory()->create([
                'tenant_id' => $this->tenant->id,
                'subject_id' => $subjectIds[$i % 3],
                'discipline_id' => $this->discipline->id,
                'status' => IntakeRequest::STATUS_UNMATCHED,
            ]);
        }

        Livewire::test(SchedulerBoard::class)->assertOk();

        $context = $this->listedEvents()[0]->context;

        // The count stays exact so the event never understates the disclosure.
        $this->assertSame($total, $context['count']);
        $this->assertCount($cap, $context['ids'], 'The id array was not capped.');
        $this->assertTrue($context['ids_truncated'] ?? false, 'Truncation happened without a marker.');
        $this->assertSame($cap, $context['id_cap']);

        // subject_ids is deduplicated and independent of the id cap.
        $this->assertEqualsCanonicalizing($subjectIds, $context['subject_ids']);
    }

    // ---- multi-list surfaces merge into one event ---------------------------------------

    public function test_the_board_records_its_two_lists_as_a_single_disclosure(): void
    {
        // scheduler-board.blade.php renders board-card (which prints a patient name) for BOTH
        // the status columns and the Needs Attention lists. They are one screen, so they are one
        // disclosure, and the event must name every patient on it rather than only the first
        // list that happened to load.
        $onBoard = $this->case('Kilo');
        $escalated = $this->case('Lima', ['status' => IntakeRequest::STATUS_ESCALATED]);
        $cancelled = $this->case('Mike', ['status' => IntakeRequest::STATUS_CANCELLED]);

        Livewire::test(SchedulerBoard::class)->assertOk();

        $events = $this->listedEvents();

        $this->assertCount(1, $events, 'The board and its Needs Attention lists produced separate events.');

        $context = $events[0]->context;

        $this->assertContains($onBoard->id, $context['ids']);
        $this->assertContains($escalated->id, $context['ids'], 'Needs Attention (escalated) was disclosed but not recorded.');
        $this->assertContains($cancelled->id, $context['ids'], 'Needs Attention (cancelled) was disclosed but not recorded.');
        $this->assertSame(3, $context['count']);
    }

    // ---- (f) tenant stamping -----------------------------------------------------------

    public function test_the_event_carries_the_tenant_it_was_rendered_for(): void
    {
        $this->case('Golf');

        Livewire::test(ListIntakeRequests::class)->assertOk();

        $event = $this->listedEvents()[0];

        $this->assertSame($this->tenant->id, $event->tenant_id);
        $this->assertSame(auth()->id(), $event->user_id);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_the_subjects_list_records_the_patients_as_both_records_and_subjects(): void
    {
        $a = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Hotel']);
        $b = Subject::factory()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'India']);

        Livewire::test(ListSubjects::class)->assertOk();

        $context = $this->listedEvents()[0]->context;

        // On this one surface the listed record IS the patient, so both arrays coincide.
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $context['ids']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $context['subject_ids']);
    }

    // ---- (h) the non-throwing contract -------------------------------------------------

    public function test_a_failing_audit_write_does_not_break_the_render(): void
    {
        $this->case('Juliet');

        // Break the audit table itself, so the write fails at the driver rather than by mocking
        // the logger away. AuditLogger swallows and logs PHI-free; the page must still render.
        DB::statement('ALTER TABLE sp_audit_events RENAME TO sp_audit_events_tmp');

        try {
            Livewire::test(ListIntakeRequests::class)
                ->assertOk()
                ->assertSee('Juliet');
        } finally {
            DB::statement('ALTER TABLE sp_audit_events_tmp RENAME TO sp_audit_events');
        }

        // And nothing was recorded, because the write genuinely failed rather than being skipped.
        $this->assertSame([], $this->listedEvents());
    }
}
