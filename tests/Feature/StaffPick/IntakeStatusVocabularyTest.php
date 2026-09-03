<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\Cases;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CompletedCases;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ListIntakeRequests;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use Tests\Feature\FeatureTest;

/**
 * Guards the invalid-enumerated-write class of defect.
 *
 * The shape: code writes a value into an enumerated column that no consumer recognizes. The
 * column is a plain varchar, so the write succeeds. Nothing throws. The row saves. And then it
 * is invisible, because every list page, count and alert filters on a set the value is not in.
 *
 * It happened twice on this column:
 *
 *   - IntakeSubmissionService wrote the literal 'pending' for two months after the
 *     2026-06-25 remap retired it, so every public referral was missing from Pending Cases,
 *     all three dashboard counts and the oldest-pending alert.
 *   - CompletedCases::STATUSES listed 'finished', a value the model has never defined, so a
 *     slot on the Discharged page pointed at nothing.
 *
 * Five tests covered the first one and all five stayed green, because each asserted the VALUE
 * the writer produced ("is it 'pending'?") and none asserted the EFFECT ("can a scheduler see
 * this case?"). These assertions are deliberately written from the consumer's side.
 */
class IntakeStatusVocabularyTest extends FeatureTest
{
    /** Tenant id well clear of anything the seeders or other tests create. */
    private const TENANT = 986001;

    /**
     * Statuses that legitimately appear on no scoped case-list page, with the reason.
     *
     * This list is a hole in the guard, so it is itself asserted below: every key must be a
     * real constant, and none may name a status a page already covers. Add an entry here with
     * a reason rather than widening a page's STATUSES to silence a failure.
     *
     * @var array<string, string>
     */
    private const INTENTIONALLY_UNLISTED = [
        IntakeRequest::STATUS_DRAFT => 'An incomplete case (Slack inbound, or a half-filled form). '
            .'Reachable on All Cases only, by design: it is not yet work anyone can action.',
        IntakeRequest::STATUS_MATCH_MADE => 'Transient. Set and advanced to MATCH_SENT inside a '
            .'single MatchDispatchService call; never a resting state.',
        IntakeRequest::STATUS_MATCH_ACCEPTED => 'Transient. Set and advanced to MATCHED inside a '
            .'single MatchDispatchService call; never a resting state.',
        IntakeRequest::STATUS_MATCH_REJECTED => 'Transient. Set and advanced back to UNMATCHED '
            .'inside a single MatchDispatchService call; never a resting state.',
    ];

    /**
     * The scoped case-list pages.
     *
     * AllCases is deliberately absent. It applies no status filter, so including it would make
     * "is this status reachable from a page?" true for every conceivable value, including
     * 'pending' — the assertion would pass vacuously and guard nothing. The question worth
     * asking is whether a status has a page that is ABOUT it.
     *
     * @var array<int, class-string>
     */
    private const SCOPED_PAGES = [
        ListIntakeRequests::class,
        Cases::class,
        CompletedCases::class,
    ];

    /**
     * Every STATUS_* constant on the model, by reflection so a newly added one is picked up
     * without anyone remembering to update this test.
     *
     * @return array<string, string> constant name => value
     */
    private function statusConstants(): array
    {
        $constants = [];

        foreach ((new ReflectionClass(IntakeRequest::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'STATUS_')) {
                $constants[$name] = $value;
            }
        }

        // Coverage guard. If the constants were ever renamed off the STATUS_ prefix (or moved
        // to an enum), this returns an empty or short list and every assertion below would
        // pass while inspecting nothing. 11 is the vocabulary as of the 2026-06-25 remap.
        $this->assertGreaterThanOrEqual(11, count($constants), sprintf(
            'Only %d STATUS_* constants found on IntakeRequest. This guard reads them by '.
            'reflection on the STATUS_ prefix; if the vocabulary moved, the guard is inspecting '.
            'nothing and must be pointed at the new source.',
            count($constants),
        ));

        // The runtime guard in IntakeRequest::booted() reads the STATUSES array, not these
        // constants. If the two ever drift, the guard would admit a value this test calls
        // invalid (or reject one it calls valid), and each would think the other had it
        // covered.
        $this->assertSame(
            array_values($constants),
            IntakeRequest::STATUSES,
            'IntakeRequest::STATUSES has drifted from the STATUS_* constants. The write-time '.
            'guard enforces the array, so anything missing from it can be written freely.',
        );

        return $constants;
    }

    /**
     * The ordering-proof half of (a).
     *
     * The table scan below can only see rows that exist when it runs, and the suite shares one
     * database in filesystem order, so a bad writer in a later file would go unseen. This
     * asserts the invariant at the write instead: no path can put an unrecognised value in the
     * column in the first place, whichever order the suite runs in.
     */
    public function test_the_model_refuses_to_save_a_status_outside_the_vocabulary(): void
    {
        $tenant = $this->createTenant();
        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);

        // Positive control first: the same call with a real status must succeed, so a failure
        // below is about the status and not about the fixture being unsaveable for some other
        // reason (a missing column, a tenant guard, a broken factory).
        $valid = IntakeRequest::create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);

        $this->assertSame(IntakeRequest::STATUS_UNMATCHED, $valid->fresh()->status);

        // 'pending' specifically: the exact literal that shipped, retired by the 2026-06-25
        // remap and still written by IntakeSubmissionService until this change.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'pending' is not an IntakeRequest status");

        IntakeRequest::create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);
    }

    /** The guard has to cover updates too; a case is far more often moved than created. */
    public function test_the_model_refuses_to_update_a_status_outside_the_vocabulary(): void
    {
        $tenant = $this->createTenant();
        $case = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => IntakeRequest::STATUS_UNMATCHED,
        ]);

        // Positive control: a legitimate transition still works.
        $case->update(['status' => IntakeRequest::STATUS_MATCH_SENT]);
        $this->assertSame(IntakeRequest::STATUS_MATCH_SENT, $case->fresh()->status);

        // And an untouched-status save on the same row is unaffected, which is what lets a
        // legacy row holding a retired value still be edited.
        $case->update(['notes' => 'still saveable']);
        $this->assertSame('still saveable', $case->fresh()->notes);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'finished' is not an IntakeRequest status");

        $case->update(['status' => 'finished']);
    }

    /**
     * (a) Every status value actually present in the table is one the model defines.
     *
     * Scans the column rather than any one writer, so it fires on an invalid value arriving
     * from any path: a service, a Filament form, a seeder, a raw DB::table() insert, or the
     * column's own DDL default.
     */
    public function test_every_status_in_the_table_is_a_defined_constant(): void
    {
        $constants = $this->statusConstants();
        $valid = array_values($constants);

        DB::beginTransaction();

        try {
            // Seed one row per constant. Inserted raw, on purpose: this guard must hold for
            // writers that never touch the model. It also makes the scan below deterministic
            // regardless of what the rest of the shared-DB suite has left behind.
            $subjectId = 986100;

            foreach ($valid as $status) {
                DB::table('sp_intake_requests')->insert([
                    'tenant_id' => self::TENANT,
                    'subject_id' => $subjectId++,
                    'status' => $status,
                ]);
            }

            $present = DB::table('sp_intake_requests')
                ->select('status')
                ->distinct()
                ->pluck('status')
                ->map(fn (?string $status): string => $status ?? '(null)')
                ->all();

            sort($present);

            // Coverage guard. The rows above mean all 11 constants MUST come back; if the scan
            // silently returned nothing (wrong table, empty result, a driver quirk) the
            // assertion below would pass against an empty set and prove nothing.
            foreach ($valid as $status) {
                $this->assertContains($status, $present, sprintf(
                    'Seeded a row with status %s but the scan did not see it, so this guard is '.
                    'not actually reading sp_intake_requests.',
                    $status,
                ));
            }

            $invalid = array_values(array_diff($present, $valid));

            $this->assertSame([], $invalid, sprintf(
                "sp_intake_requests holds %d status value(s) that IntakeRequest does not define:\n\n  %s\n\n".
                "A value outside the vocabulary saves without error and is then invisible: every\n".
                "case-list page, dashboard count and alert filters on the defined set, so the row\n".
                "surfaces only on All Cases. Find the writer (grep for \"'status' =>\") and give it\n".
                "an IntakeRequest::STATUS_* constant.\n\nDefined: %s",
                count($invalid),
                implode("\n  ", $invalid),
                implode(', ', $valid),
            ));
        } finally {
            DB::rollBack();
        }
    }

    /**
     * (b) Every defined status has somewhere to show up, and every page slot points at a real
     * status.
     *
     * The forward direction catches a status nothing displays, which is what 'pending' was.
     * The reverse catches a page filtering on a value that cannot exist, which is what
     * 'finished' was. Both are the same defect seen from opposite ends.
     */
    public function test_every_status_is_reachable_from_a_scoped_page_or_explicitly_exempt(): void
    {
        $constants = $this->statusConstants();
        $valid = array_values($constants);

        $covered = [];

        foreach (self::SCOPED_PAGES as $page) {
            $this->assertTrue(
                defined($page.'::STATUSES'),
                "{$page} has no STATUSES constant, so this guard cannot see what it filters on.",
            );

            $covered = array_merge($covered, constant($page.'::STATUSES'));
        }

        $covered = array_values(array_unique($covered));

        // Coverage guard: if the pages stopped exposing STATUSES (or started returning empty
        // arrays) the forward assertion would report every status as unreachable, and the
        // reverse would pass vacuously. Both failure directions are caught here first.
        $this->assertGreaterThanOrEqual(
            count(self::SCOPED_PAGES),
            count($covered),
            'The scoped pages contribute fewer statuses than there are pages, so at least one '.
            'filters on nothing and this guard is not inspecting real page configuration.',
        );

        // Reverse: no page may filter on a value the model does not define.
        $phantom = array_values(array_diff($covered, $valid));

        $this->assertSame([], $phantom, sprintf(
            "These values appear in a case-list page's STATUSES but are not IntakeRequest ".
            "constants, so that slice of the page can never match a row:\n\n  %s",
            implode("\n  ", $phantom),
        ));

        // The exemption list is a hole in the forward assertion, so guard the hole.
        foreach (array_keys(self::INTENTIONALLY_UNLISTED) as $exempt) {
            $this->assertContains($exempt, $valid, sprintf(
                "INTENTIONALLY_UNLISTED names '%s', which is not a status constant. A typo here ".
                'silently excuses a real status from the assertion below.',
                $exempt,
            ));

            $this->assertNotContains($exempt, $covered, sprintf(
                "INTENTIONALLY_UNLISTED names '%s', but a scoped page already covers it. Drop ".
                'the exemption rather than leaving a blanket that could hide a future status.',
                $exempt,
            ));
        }

        // Forward: every status is on a page about it, or is exempt with a stated reason.
        $unreachable = array_values(array_diff($valid, $covered, array_keys(self::INTENTIONALLY_UNLISTED)));

        $this->assertSame([], $unreachable, sprintf(
            "These statuses are defined on IntakeRequest but appear on no scoped case-list page:\n\n  %s\n\n".
            "A case in one of them is invisible to schedulers (All Cases aside), which is exactly\n".
            "how 'pending' hid public referrals for two months. Either add the status to a page's\n".
            'STATUSES, or add it to INTENTIONALLY_UNLISTED with the reason it needs no page.',
            implode("\n  ", $unreachable),
        ));
    }

    /**
     * Same defect class, one layer up: a status with no label renders as a raw snake_case
     * string in the table pill, the view page and the filter dropdown.
     */
    public function test_every_status_has_a_display_label(): void
    {
        $valid = array_values($this->statusConstants());
        $labelled = array_keys(IntakeRequestResource::statusOptions());

        // Coverage guard: an empty options array would make both diffs below trivially empty.
        $this->assertNotEmpty($labelled, 'IntakeRequestResource::statusOptions() returned nothing.');

        $this->assertSame([], array_values(array_diff($valid, $labelled)), sprintf(
            'These statuses have no entry in IntakeRequestResource::statusOptions(), so they '.
            "render as a raw value wherever a label is shown:\n  %s",
            implode("\n  ", array_diff($valid, $labelled)),
        ));

        $this->assertSame([], array_values(array_diff($labelled, $valid)), sprintf(
            "statusOptions() labels these values, which are not statuses the model defines:\n  %s",
            implode("\n  ", array_diff($labelled, $valid)),
        ));
    }
}
