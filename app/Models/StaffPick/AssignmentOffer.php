<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentOffer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_assignment_offers';

    protected $fillable = [
        'intake_request_id',
        'provider_id',
        'tenant_id',
        'offer_sequence',
        'distance_miles',
        'match_score',
        'offered_at',
        'expires_at',
        'response',
        'responded_at',
        'decline_reason_id',
    ];

    protected function casts(): array
    {
        return [
            'offer_sequence' => 'integer',
            'distance_miles' => 'decimal:2',
            'match_score' => 'decimal:4',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
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
}
