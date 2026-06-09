<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Shared reference table — not tenant-scoped.
 */
class Language extends Model
{
    use HasFactory;

    protected $table = 'sp_languages';

    protected $fillable = [
        'name',
        'code',
    ];

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'sp_provider_languages')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
