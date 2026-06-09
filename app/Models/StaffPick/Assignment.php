<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use BelongsToTenant, HasFactory;

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
}
