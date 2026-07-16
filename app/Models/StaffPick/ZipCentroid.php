<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Model;

/**
 * US ZIP to centroid lat/lng. PUBLIC reference data (US Census ZCTA + GeoNames gap-fill),
 * shared across every tenant. Deliberately NOT tenant-scoped and NOT a BearsTenantPhi model:
 * a ZIP centroid is public, not PHI, and geocoding runs on model save in contexts with no
 * tenant (queue, console, seeder). Adding tenant scope here would make the H3b read guard throw.
 */
class ZipCentroid extends Model
{
    protected $table = 'sp_zip_centroids';

    protected $primaryKey = 'zip';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['zip', 'latitude', 'longitude', 'source'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
