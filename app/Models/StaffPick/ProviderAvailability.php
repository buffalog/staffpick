<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inherits tenancy through its parent provider; not directly tenant-scoped.
 */
class ProviderAvailability extends Model
{
    use HasFactory;

    protected $table = 'sp_provider_availability';

    protected $fillable = [
        'provider_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'provider_id' => 'integer',
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
