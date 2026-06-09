<?php

namespace App\Models\StaffPick;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inherits tenancy through its parent provider; not directly tenant-scoped.
 */
class ProviderCredential extends Model
{
    use HasFactory;

    protected $table = 'sp_provider_credentials';

    protected $fillable = [
        'provider_id',
        'document_type_id',
        'document_number',
        'issued_at',
        'expires_at',
        'status',
        'file_path',
        'notes',
        'verified_at',
        'verified_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(CredentialDocumentType::class, 'document_type_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
