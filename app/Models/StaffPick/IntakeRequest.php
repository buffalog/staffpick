<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntakeRequest extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

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

    // Why a case escalated. POOL_EXHAUSTED is the genuine "we tried everyone" case; the
    // rest are structural — a missing prerequisite no amount of provider availability fixes.
    public const ESCALATION_POOL_EXHAUSTED = 'pool_exhausted';

    public const ESCALATION_NEEDS_COORDINATES = 'needs_coordinates';

    public const ESCALATION_NO_DISCIPLINE = 'no_discipline';

    public const ESCALATION_NO_SUBJECT = 'no_subject';

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
