<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderTier extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_provider_tiers';

    protected $fillable = [
        'tenant_id',
        'name',
        'priority',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'tier_id');
    }
}
