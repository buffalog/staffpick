<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentOffer extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $table = 'sp_assignment_offers';

    protected $fillable = [
        'intake_request_id',
        'provider_id',
        'tenant_id',
        'offer_sequence',
        'distance_miles',
        'match_score',
        'language_warning',
        'offered_at',
        'expires_at',
        'response',
        'responded_at',
        'decline_reason_id',
        'status',
        'delivery_channel',
        'token',
        'tier_at_offer',
        'response_window_minutes',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'offer_sequence' => 'integer',
            'distance_miles' => 'decimal:2',
            'match_score' => 'decimal:4',
            'language_warning' => 'boolean',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'response_window_minutes' => 'integer',
            'expired_at' => 'datetime',
        ];
    }

    /** Sent and awaiting a response. */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->offered_at !== null;
    }

    /** Queued but not yet sent. */
    public function isQueued(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->offered_at === null;
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
}
