<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A public provider self-serve onboarding application — a resumable draft (keyed by
 * application_token) that staff review and, on approval, map into a real sp_providers
 * record. Created in a guest context, so tenant_id is always set explicitly.
 */
class ProviderApplication extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'sp_provider_applications';

    protected $fillable = [
        'tenant_id',
        'application_token',
        'status',
        'rejection_reason',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'first_name',
        'last_name',
        'email',
        'phone',
        'street_address',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'discipline',
        'gender',
        'specialties',
        'service_zones',
        'preferred_radius',
        'maximum_radius',
        'is_contractor',
        'credential_uploads',
        'current_step',
        'step_data',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'specialties' => 'array',
            'service_zones' => 'array',
            'credential_uploads' => 'array',
            'step_data' => 'array',
            'is_contractor' => 'boolean',
            'preferred_radius' => 'integer',
            'maximum_radius' => 'integer',
            'current_step' => 'integer',
            'reviewed_by' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
