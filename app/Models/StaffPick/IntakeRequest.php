<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\StaffPick\Concerns\RecordsPhiAudit;
use App\Models\StaffPick\Contracts\BearsTenantPhi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class IntakeRequest extends Model implements BearsTenantPhi
{
    use BelongsToTenant, HasFactory, RecordsPhiAudit, SoftDeletes;

    protected $table = 'sp_intake_requests';

    // Match/dispatch status vocabulary (lowercase-snake). MATCH_MADE / MATCH_ACCEPTED /
    // MATCH_REJECTED are transient — set and immediately advanced within a single
    // MatchDispatchService call, never persisted to a resting state.
    public const STATUS_DRAFT = 'draft';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_MATCH_MADE = 'match_made';

    public const STATUS_MATCH_SENT = 'match_sent';

    public const STATUS_MATCH_ACCEPTED = 'match_accepted';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_MATCH_REJECTED = 'match_rejected';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The complete vocabulary, in pipeline order. Kept as an array so both the write-time
     * guard in booted() and the consumers that filter on status have one list to read;
     * IntakeStatusVocabularyTest asserts it stays in step with the STATUS_* constants.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UNMATCHED,
        self::STATUS_MATCH_MADE,
        self::STATUS_MATCH_SENT,
        self::STATUS_MATCH_ACCEPTED,
        self::STATUS_MATCHED,
        self::STATUS_MATCH_REJECTED,
        self::STATUS_ESCALATED,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    // Why a case escalated. POOL_EXHAUSTED is the genuine "we tried everyone" case; the
    // rest are structural — a missing prerequisite no amount of provider availability fixes.
    public const ESCALATION_POOL_EXHAUSTED = 'pool_exhausted';

    public const ESCALATION_NEEDS_COORDINATES = 'needs_coordinates';

    public const ESCALATION_NO_DISCIPLINE = 'no_discipline';

    public const ESCALATION_NO_SUBJECT = 'no_subject';

    /** Crockford-style base32 for reference numbers, minus the ambiguous glyphs I/O/0/1. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'tenant_id',
        'reference_number',
        'subject_id',
        'referral_source_id',
        'discipline_id',
        'office_id',
        'assigner_user_id',
        'status',
        'on_hold_reason_id',
        'cancellation_reason_id',
        'status_notes',
        'authorization_number',
        'start_date',
        'end_date',
        'frequency',
        'visits_authorized',
        'visits_completed',
        'visit_type',
        'radius_miles',
        'manual_assignment',
        'needs_emr_transition',
        'paperwork_complete',
        'emr_id',
        'slack_channel_id',
        'notes',
        'referring_clinician_name',
        'referring_clinician_phone',
        'is_partial_staffing',
        'assistant_clinician_name',
        'lead_clinician_id',
        'requested_provider_id',
        'evaluation_date',
        'acknowledged_at',
        'matched_at',
        'assigned_at',
        'closed_at',
        'current_match_provider_id',
        'cascade_attempt',
        'escalated_at',
        'escalation_reason',
        'last_match_sent_at',
    ];

    /** Staff-facing sentence for an escalation reason: what's wrong and what to do about it. */
    public static function escalationReasonLabel(?string $reason): string
    {
        return match ($reason) {
            self::ESCALATION_NEEDS_COORDINATES => __('No map coordinates — geocode the subject address, then re-run matching.'),
            self::ESCALATION_NO_DISCIPLINE => __('No discipline set — set one, then re-run matching.'),
            self::ESCALATION_NO_SUBJECT => __('No subject on file — add the subject, then re-run matching.'),
            default => __('Provider pool exhausted — manual intervention required.'),
        };
    }

    /**
     * Every intake gets a human-readable reference, whatever created it.
     *
     * This lives on the model rather than in IntakeSubmissionService because the reference is
     * an invariant of the record, not of one writer. Only the public and Slack paths called the
     * generator; the staff Filament form did not, so a staff-created case carried NULL and then
     * rendered as a blank Reference column, a blank board card, an ICS UID of "case-{id}", an em
     * dash on the Slack card, and three mail subject lines ending in a bare colon.
     *
     * Registered in booted(), which runs after bootTraits(), so BelongsToTenant's own creating
     * listener has already resolved and stamped tenant_id (or thrown) by the time this fires.
     * That ordering is what makes the per-tenant uniqueness check below meaningful.
     */
    protected static function booted(): void
    {
        static::creating(function (self $intake): void {
            if (blank($intake->reference_number)) {
                $intake->reference_number = self::generateReferenceNumber((int) $intake->tenant_id);
            }
        });

        // Fail closed on a status outside the vocabulary.
        //
        // `status` is a plain varchar, so an unrecognised value used to save cleanly and then
        // vanish: every case-list page, dashboard count and alert filters on the defined set,
        // so the row surfaced only on All Cases. That is how public referrals sat in 'pending'
        // for two months after the 2026-06-25 remap retired it, with five green tests.
        //
        // This mirrors BelongsToTenant's write guard, and for the same reason: silence is the
        // dangerous outcome. A referral nobody can see is worse than a 500 at the write.
        //
        // Guarded on isDirty() so a legacy row holding a retired value can still be updated
        // (or corrected) rather than becoming permanently unsaveable. Absent on create, the
        // column default applies, which the same-day migration set to a real status.
        static::saving(function (self $intake): void {
            if (! $intake->isDirty('status')) {
                return;
            }

            if (! in_array($intake->status, self::STATUSES, true)) {
                throw new InvalidArgumentException(sprintf(
                    "'%s' is not an IntakeRequest status. Use one of the STATUS_* constants: %s. ".
                    'An unrecognised value saves without error and is then invisible to every '.
                    'case-list page, dashboard count and alert.',
                    (string) $intake->status,
                    implode(', ', self::STATUSES),
                ));
            }
        });
    }

    /**
     * A short, professional reference (R-XXXXXX) that does not leak intake volume.
     *
     * Deliberately more conservative than sp_intake_requests_reference_unique, whose predicate
     * is `WHERE reference_number IS NOT NULL AND deleted_at IS NULL`: withoutGlobalScopes()
     * drops the SoftDeletes scope as well as the tenant scope, so this also refuses a candidate
     * held by a soft-deleted row that the index would in fact permit. Erring that way can only
     * cost an extra loop; erring the other way would hand back a value the index rejects.
     * The index remains the arbiter under concurrency, which this loop cannot see.
     */
    public static function generateReferenceNumber(int $tenantId): string
    {
        do {
            $candidate = 'R-'.collect(range(1, 6))
                ->map(fn (): string => self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)])
                ->implode('');
        } while (
            self::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('reference_number', $candidate)
                ->exists()
        );

        return $candidate;
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'subject_id' => 'integer',
            'referral_source_id' => 'integer',
            'discipline_id' => 'integer',
            'office_id' => 'integer',
            'assigner_user_id' => 'integer',
            'on_hold_reason_id' => 'integer',
            'cancellation_reason_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'visits_authorized' => 'integer',
            'visits_completed' => 'integer',
            'radius_miles' => 'integer',
            'manual_assignment' => 'boolean',
            'needs_emr_transition' => 'boolean',
            'paperwork_complete' => 'boolean',
            'is_partial_staffing' => 'boolean',
            'lead_clinician_id' => 'integer',
            'requested_provider_id' => 'integer',
            'acknowledged_at' => 'datetime',
            'matched_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
            'current_match_provider_id' => 'integer',
            'cascade_attempt' => 'integer',
            'escalated_at' => 'datetime',
            'last_match_sent_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * The lead clinician assigned post-matching. FK-less column (SQL Server cascade
     * rules), but the belongsTo still resolves the related Provider.
     */
    public function leadClinician(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'lead_clinician_id');
    }

    public function requestedProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'requested_provider_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_user_id');
    }

    public function onHoldReason(): BelongsTo
    {
        return $this->belongsTo(OnHoldReason::class, 'on_hold_reason_id');
    }

    public function cancellationReason(): BelongsTo
    {
        return $this->belongsTo(CancellationReason::class, 'cancellation_reason_id');
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'sp_intake_request_specialties')
            ->withTimestamps();
    }

    public function files(): HasMany
    {
        return $this->hasMany(IntakeRequestFile::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->where('is_current', true);
    }

    public function assignmentOffers(): HasMany
    {
        return $this->hasMany(AssignmentOffer::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(IntakeRequestHistory::class);
    }
}
