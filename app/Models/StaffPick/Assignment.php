<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Observers\StaffPick\AssignmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(AssignmentObserver::class)]
class Assignment extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_OFFERED = 'offered';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'sp_assignments';

    protected $fillable = [
        'intake_request_id',
        'provider_id',
        'tenant_id',
        'status',
        'offered_at',
        'offer_expires_at',
        'responded_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'decline_reason_id',
        'decline_notes',
        'is_manual',
        'is_current',
        'rate',
        'rate_type',
        'notes',
        'assigned_by_user_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_manual' => 'boolean',
            'is_current' => 'boolean',
            'rate' => 'decimal:2',
            'assigned_at' => 'datetime',
        ];
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function declineReason(): BelongsTo
    {
        return $this->belongsTo(DeclineReason::class, 'decline_reason_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(ProviderSurvey::class);
    }
}
