<?php

namespace App\Models\StaffPick;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inherits tenancy through its parent intake request; not directly tenant-scoped.
 */
class IntakeRequestFile extends Model
{
    use HasFactory;

    protected $table = 'sp_intake_request_files';

    protected $fillable = [
        'intake_request_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'label',
        'visibility',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
