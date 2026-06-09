<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inherits tenancy through its parent provider; not directly tenant-scoped.
 */
class ProviderServiceZone extends Model
{
    use HasFactory;

    protected $table = 'sp_provider_service_zones';

    protected $fillable = [
        'provider_id',
        'name',
        'polygon_geojson',
        'bbox_north',
        'bbox_south',
        'bbox_east',
        'bbox_west',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'bbox_north' => 'decimal:7',
            'bbox_south' => 'decimal:7',
            'bbox_east' => 'decimal:7',
            'bbox_west' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
