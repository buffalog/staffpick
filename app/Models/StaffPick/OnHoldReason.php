<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnHoldReason extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_on_hold_reasons';

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
        return $this->hasMany(IntakeRequest::class, 'on_hold_reason_id');
    }
}
