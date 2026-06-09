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
        'acknowledged_at',
        'matched_at',
        'assigned_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'visits_authorized' => 'integer',
            'visits_completed' => 'integer',
            'radius_miles' => 'integer',
            'manual_assignment' => 'boolean',
            'needs_emr_transition' => 'boolean',
            'paperwork_complete' => 'boolean',
            'acknowledged_at' => 'datetime',
            'matched_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
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
