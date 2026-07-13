<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeclineReason extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_decline_reasons';

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

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'decline_reason_id');
    }

    public function assignmentOffers(): HasMany
    {
        return $this->hasMany(AssignmentOffer::class, 'decline_reason_id');
    }
}
