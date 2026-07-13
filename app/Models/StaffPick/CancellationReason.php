<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CancellationReason extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_cancellation_reasons';

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function intakeRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class, 'cancellation_reason_id');
    }
}
