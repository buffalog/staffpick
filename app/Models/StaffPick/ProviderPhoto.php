<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\StoresSqlServerBlob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider's profile photo, stored as a VARBINARY(MAX) BLOB in Azure SQL (see
 * {@see StoresSqlServerBlob}). One row per provider, replace-in-place. Inherits tenancy
 * transitively through its provider; not directly tenant-scoped. `content` is never
 * fillable or SELECTed by default — bytes go through storeContent()/readContent().
 */
class ProviderPhoto extends Model
{
    use HasFactory, StoresSqlServerBlob;

    /** Accepted upload extensions — images only (a photo is never a PDF/Word doc). */
    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'heic'];

    /** Max upload size in kilobytes (5 MB) — tighter than credentials since it's read often. */
    public const MAX_SIZE_KB = 5120;

    protected $table = 'sp_provider_photos';

    protected $fillable = [
        'provider_id',
        'mime_type',
        'file_size',
        'updated_by_user_id',
    ];

    protected $hidden = [
        'content',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
