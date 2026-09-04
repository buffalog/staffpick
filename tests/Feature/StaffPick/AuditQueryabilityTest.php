<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Models\StaffPick\AuditEvent;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\AuditLogger;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\FeatureTest;

/**
 * The audit log has to answer one question honestly: everything that happened to THIS patient.
 *
 * Before this, it could not. subject_id is a scalar and is NULL on every 'listed' row, because a
 * list discloses many patients at once, so the patient ids lived only inside a text column that
 * Postgres cannot run a containment query against. Filtering by patient returned single-record
 * views only and silently omitted every list they appeared in: a confidently incomplete answer,
 * which is worse than an obviously incomplete one.
 */
class AuditQueryabilityTest extends FeatureTest
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

        AuditEvent::query()->forceDelete();
    }

    private function subject(string $last): Subject
    {
        return Subject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Pat',
            'last_name' => $last,
        ]);
    }

    /** @return array<int, AuditEvent> */
    private function listed(): array
    {
        return AuditEvent::query()->where('action', 'listed')->orderBy('id')->get()->all();
    }

    // ---- (a) the filter sees both halves of the trail -----------------------------------

    public function test_the_patient_filter_returns_both_single_record_and_list_disclosures(): void
    {
        $alpha = $this->subject('Alpha');
        $bravo = $this->subject('Bravo');
        $charlie = $this->subject('Charlie');

        // Creating the Subjects themselves writes 'created' events (RecordsPhiAudit). Clear
        // them so the assertions below are about disclosure, not fixture noise.
        AuditEvent::query()->forceDelete();

        // A single-record disclosure of Alpha: subject_id is set on this one.
        app(AuditLogger::class)->record('viewed', $alpha);

        // A list disclosure naming Alpha and Bravo but NOT Charlie: subject_id is null, the ids
        // live in context.
        app(AuditLogger::class)->record('listed', null, [
            'surface' => 'TestSurface',
            'count' => 2,
            'ids' => [901, 902],
            'subject_ids' => [$alpha->id, $bravo->id],
        ]);

        // Charlie's own single-record disclosure, which must not leak into the other filters.
        app(AuditLogger::class)->record('viewed', $charlie);

        $this->assertSame(3, AuditEvent::query()->count(), 'fixture did not write what the test assumes');

        $forAlpha = $this->filterBySubject($alpha->id);

        $this->assertEqualsCanonicalizing(
            ['viewed', 'listed'],
            $forAlpha->pluck('action')->all(),
            "Alpha's history must include the list they appeared in, not just the record that was opened.",
        );

        // Bravo was only ever disclosed in the list, never opened individually.
        $forBravo = $this->filterBySubject($bravo->id);
        $this->assertSame(['listed'], $forBravo->pluck('action')->all());

        // Charlie was in neither the list nor Alpha's view.
        $forCharlie = $this->filterBySubject($charlie->id);
        $this->assertSame(['viewed'], $forCharlie->pluck('action')->all());
        $this->assertNotContains(
            'listed',
            $forCharlie->pluck('action')->all(),
            'A patient who was never on screen must not appear in a list disclosure.',
        );
    }

    /**
     * Runs the resource's real filter, so the test exercises the query the viewer issues rather
     * than a reimplementation of it.
     *
     * @return Collection<int, AuditEvent>
     */
    private function filterBySubject(int $subjectId): Collection
    {
        return Livewire::test(ListAuditEvents::class)
            ->filterTable('subject', ['subject_id' => $subjectId])
            ->assertOk()
            ->instance()
            ->getFilteredTableQuery()
            ->get();
    }

    // ---- (b) the migration preserved the real row shapes ---------------------------------

    public function test_the_jsonb_column_round_trips_every_context_shape_found_in_production(): void
    {
        // The four shapes actually present on the authoritative environment when the migration
        // was written (33 rows: 9 NULL, 24 valid JSON, 0 invalid), plus NULL. Asserted against
        // what was really there rather than an invented fixture.
        $shapes = [
            'login_failed' => ['email' => 'someone@example.com'],
            'created' => ['changes' => ['first_name' => 'Alba', 'last_name' => 'Ruiz']],
            'updated' => ['changes' => ['first_name' => ['old' => 'Alba', 'new' => 'Bianca']]],
            'viewed' => ['actor_label' => 'offer-token'],
        ];

        foreach ($shapes as $action => $context) {
            app(AuditLogger::class)->record($action, null, $context);
        }

        app(AuditLogger::class)->record('logout');

        foreach ($shapes as $action => $context) {
            $stored = AuditEvent::query()->where('action', $action)->latest('id')->first();

            $this->assertNotNull($stored, "no row written for {$action}");
            $this->assertEqualsCanonicalizing(
                $context,
                $stored->context,
                "context for '{$action}' did not survive the jsonb round trip",
            );
        }

        $this->assertNull(
            AuditEvent::query()->where('action', 'logout')->latest('id')->first()->context,
            'A NULL context must stay NULL through jsonb, not become an empty object.',
        );

        // The column really is jsonb, not text that happens to hold JSON.
        $type = DB::selectOne("select data_type from information_schema.columns where table_name='sp_audit_events' and column_name='context'");
        $this->assertSame('jsonb', $type->data_type);
    }

    // ---- (c) (d) (e) (f) the collapse ---------------------------------------------------

    /** @param array<int, int> $ids */
    private function renderListing(array $ids, string $surface = 'TestSurface'): void
    {
        app(AuditLogger::class)->record('listed', null, [
            'surface' => $surface,
            'count' => count($ids),
            'ids' => $ids,
            'subject_ids' => $ids,
        ]);
    }

    public function test_two_identical_renders_collapse_into_one_row_with_a_repeat_count(): void
    {
        $this->renderListing([1, 2, 3]);
        $first = $this->listed()[0];
        $firstOccurredAt = $first->occurred_at->copy();

        $this->travel(2)->minutes();
        $this->renderListing([1, 2, 3]);

        $rows = $this->listed();

        $this->assertCount(1, $rows, 'An identical repeat should accrue, not add a row.');
        $this->assertSame(2, $rows[0]->repeat_count);

        // occurred_at is the START of the access window and must not move: "accessed from 14:02
        // through 22:10" is the useful record, and advancing it would destroy the start.
        $this->assertEquals($firstOccurredAt, $rows[0]->occurred_at, 'occurred_at must stay the first occurrence.');
        $this->assertGreaterThan($rows[0]->occurred_at, $rows[0]->last_occurred_at, 'last_occurred_at must advance.');
    }

    public function test_a_different_id_set_is_a_new_disclosure_not_a_collapse(): void
    {
        $this->renderListing([1, 2, 3]);
        $this->travel(1)->minutes();

        // Same count, different people. Compared as a set, so this must NOT collapse.
        $this->renderListing([1, 2, 4]);

        $rows = $this->listed();

        $this->assertCount(2, $rows, 'A changed id set is a different disclosure and needs its own row.');
        $this->assertSame(1, $rows[0]->repeat_count);
        $this->assertSame(1, $rows[1]->repeat_count);
    }

    public function test_the_same_set_in_a_different_order_still_collapses(): void
    {
        $this->renderListing([3, 1, 2]);
        $this->travel(1)->minutes();
        $this->renderListing([1, 2, 3]);

        $rows = $this->listed();

        $this->assertCount(1, $rows, 'Ordering is a query artefact, not a different disclosure.');
        $this->assertSame(2, $rows[0]->repeat_count);
    }

    public function test_a_render_past_the_collapse_window_starts_a_new_row(): void
    {
        $this->renderListing([1, 2, 3]);

        $this->travel(AuditLogger::LISTED_COLLAPSE_WINDOW_MINUTES + 1)->minutes();
        $this->renderListing([1, 2, 3]);

        $this->assertCount(
            2,
            $this->listed(),
            'A tab reopened after a long gap is a new access, not a continuation of the old one.',
        );
    }

    public function test_a_render_by_a_different_user_never_collapses(): void
    {
        $this->renderListing([1, 2, 3]);

        $this->actingAs($this->createTenantAdmin($this->tenant));
        $this->travel(1)->minutes();
        $this->renderListing([1, 2, 3]);

        $rows = $this->listed();

        $this->assertCount(2, $rows, 'Two people seeing the same screen are two disclosures.');
        $this->assertNotSame($rows[0]->user_id, $rows[1]->user_id);
    }

    public function test_a_render_on_a_different_surface_never_collapses(): void
    {
        $this->renderListing([1, 2, 3], 'SurfaceOne');
        $this->travel(1)->minutes();
        $this->renderListing([1, 2, 3], 'SurfaceTwo');

        $this->assertCount(2, $this->listed(), 'Different surfaces are different disclosures.');
    }

    public function test_a_render_in_a_different_tenant_never_collapses(): void
    {
        $this->renderListing([1, 2, 3]);

        $other = $this->createTenant();
        Filament::setTenant($other);
        $this->travel(1)->minutes();
        $this->renderListing([1, 2, 3]);

        $rows = $this->listed();

        $this->assertCount(2, $rows);
        $this->assertNotSame($rows[0]->tenant_id, $rows[1]->tenant_id);
    }

    public function test_only_listed_events_ever_collapse(): void
    {
        $subject = $this->subject('Delta');

        app(AuditLogger::class)->record('viewed', $subject);
        $this->travel(1)->minutes();
        app(AuditLogger::class)->record('viewed', $subject);

        $this->assertSame(
            2,
            AuditEvent::query()->where('action', 'viewed')->count(),
            'Opening the same record twice is two accesses and must stay two rows.',
        );
    }

    // ---- immutability -------------------------------------------------------------------

    public function test_an_audit_event_still_refuses_every_change_but_the_two_accruable_fields(): void
    {
        $this->renderListing([1, 2, 3]);
        $event = $this->listed()[0];

        foreach (['action' => 'viewed', 'user_id' => 999, 'subject_id' => 5, 'occurred_at' => now()->subDay()] as $field => $value) {
            $fresh = AuditEvent::query()->find($event->id);
            $fresh->{$field} = $value;

            try {
                $fresh->save();
                $this->fail("Changing {$field} on a recorded audit event was allowed.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
    }

    public function test_the_accruable_fields_may_only_move_forward(): void
    {
        $this->renderListing([1, 2, 3]);
        $this->travel(1)->minutes();
        $this->renderListing([1, 2, 3]);

        $event = AuditEvent::query()->find($this->listed()[0]->id);

        // A collapsed row can never be made to claim LESS disclosure than it already does.
        $event->repeat_count = 1;
        try {
            $event->save();
            $this->fail('repeat_count was allowed to decrease.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('only increase', $e->getMessage());
        }

        $event = AuditEvent::query()->find($event->id);
        $event->last_occurred_at = $event->last_occurred_at->copy()->subHour();
        try {
            $event->save();
            $this->fail('last_occurred_at was allowed to move backwards.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('only advance', $e->getMessage());
        }
    }

    public function test_audit_events_still_cannot_be_deleted(): void
    {
        $this->renderListing([1, 2, 3]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $this->listed()[0]->delete();
    }

    // ---- (g) the non-throwing contract, still ------------------------------------------

    public function test_a_failing_accrual_does_not_throw_into_the_caller(): void
    {
        $this->renderListing([1, 2, 3]);

        // Break the table so the collapse path's own SELECT/UPDATE fails, not just the insert.
        DB::statement('ALTER TABLE sp_audit_events RENAME TO sp_audit_events_tmp');

        try {
            app(AuditLogger::class)->record('listed', null, [
                'surface' => 'TestSurface',
                'count' => 3,
                'ids' => [1, 2, 3],
                'subject_ids' => [1, 2, 3],
            ]);
            $this->addToAssertionCount(1); // got here: nothing was thrown
        } finally {
            DB::statement('ALTER TABLE sp_audit_events_tmp RENAME TO sp_audit_events');
        }

        $this->assertSame(1, count($this->listed()), 'The failed write must not have accrued.');
    }
}
