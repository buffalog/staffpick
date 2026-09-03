<?php

namespace App\Filament\Concerns;

use App\Services\StaffPick\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Records ONE 'listed' HIPAA audit event per render of a surface that puts patient identifiers
 * on screen: a list, a card grid, a board, a calendar, or a banner naming a patient.
 *
 * Sibling to {@see LogsRecordView}, not a replacement. 'viewed' means "opened one patient's
 * record"; 'listed' means "saw these patients side by side". Both are disclosures and both are
 * recorded; a page can legitimately produce neither, one, or (across a session) both.
 *
 * ONE EVENT PER RENDER, NOT PER RECORD. All Cases showing 50 cards writes a single row naming
 * fifty patients, not fifty rows. Three refreshes write three rows, not 150. The table is
 * retained six years, and a row-per-record design buries the signal it exists to surface: during
 * an incident you want "user X saw patients 1-50 on All Cases at 22:04", not fifty fragments to
 * reassemble. {@see $listedEventWritten} enforces the once-per-render half of that; Filament may
 * resolve the same record set several times while rendering.
 *
 * NOTHING IS RECORDED FOR AN EMPTY RESULT SET. No patient was disclosed, so there is nothing to
 * account for, and a scheduler paging through empty filters would otherwise flood the stream.
 *
 * Writes go through {@see AuditLogger::record()} like every other event: synchronous (PHI must
 * never enter a queue payload) and non-throwing, so a failed audit write can never break a page
 * render. This trait adds no error handling of its own precisely so that contract stays in one
 * place.
 */
trait LogsRecordList
{
    /**
     * Hard ceiling on how many ids are written into one event.
     *
     * The paginated tables cannot exceed it: Filament's page options here are [5, 10, 25, 50].
     * The cap exists for the UNPAGINATED surfaces, which are the ones that can actually run
     * away: SchedulerBoard, both calendars and the dashboard banner all ->get() the whole
     * matching set, so a tenant with thousands of open cases would otherwise write a
     * multi-kilobyte blob on every render into a table retained six years.
     * Past it the count is still exact and 'ids_truncated' says so, so the event never quietly
     * implies a smaller disclosure than actually happened.
     */
    public const LISTED_ID_CAP = 200;

    /** Guards the once-per-render contract within a single component instance / request. */
    private bool $listedEventWritten = false;

    /**
     * Record the patients disclosed by this render.
     *
     * @param  iterable<mixed>  $records  the records actually on screen, not the whole query
     * @param  array<string, mixed>  $context  surface-specific extras (filters, search, page)
     */
    public function logListedRecords(iterable $records, array $context = []): void
    {
        if ($this->listedEventWritten) {
            return;
        }

        $collection = $records instanceof Collection ? $records : collect($records);

        // Nothing on screen means nothing disclosed. Not an event.
        if ($collection->isEmpty()) {
            return;
        }

        $this->listedEventWritten = true;

        $ids = $collection
            ->map(fn ($record): mixed => $record instanceof Model ? $record->getKey() : ($record['id'] ?? null))
            ->filter(fn ($id): bool => $id !== null)
            ->values();

        // The patients behind those records. Recorded separately because the accounting question
        // is "who was disclosed", and for most surfaces the listed record is an IntakeRequest,
        // not the Subject. On the Subjects list the two lists coincide, which is correct.
        $subjectIds = $collection
            ->map(fn ($record): mixed => $this->listedSubjectId($record))
            ->filter(fn ($id): bool => $id !== null)
            ->unique()
            ->values();

        app(AuditLogger::class)->record('listed', null, array_merge([
            'surface' => static::class,
            // Counted from the collection, not from the (possibly capped) id array, so an
            // aggregate query never has to parse json to answer "how many were disclosed".
            'count' => $collection->count(),
            'ids' => $ids->take(self::LISTED_ID_CAP)->all(),
            'subject_ids' => $subjectIds->take(self::LISTED_ID_CAP)->all(),
        ],
            $ids->count() > self::LISTED_ID_CAP ? ['ids_truncated' => true, 'id_cap' => self::LISTED_ID_CAP] : [],
            $context,
        ));
    }

    /**
     * The patient behind one listed record: a Subject is its own patient, anything else carries
     * a subject_id. Mirrors AuditLogger::resolveSubjectId() so both halves of the stream agree
     * on what "the patient" means.
     */
    private function listedSubjectId(mixed $record): ?int
    {
        if (! $record instanceof Model) {
            return null;
        }

        if (class_basename($record) === 'Subject') {
            return (int) $record->getKey();
        }

        $subjectId = $record->getAttribute('subject_id');

        return $subjectId !== null ? (int) $subjectId : null;
    }
}
