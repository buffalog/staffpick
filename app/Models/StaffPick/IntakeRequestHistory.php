<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\StaffPick\Contracts\BearsTenantPhi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeRequestHistory extends Model implements BearsTenantPhi
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_intake_request_history';

    protected $fillable = [
        'intake_request_id',
        'tenant_id',
        'user_id',
        'event',
        'from_status',
        'to_status',
        'notes',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'intake_request_id' => 'integer',
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
