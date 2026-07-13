<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralSourceGroup extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_referral_source_groups';

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

    public function referralSources(): HasMany
    {
        return $this->hasMany(ReferralSource::class, 'group_id');
    }
}
