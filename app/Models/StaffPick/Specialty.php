<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    use BelongsToTenant, HasFactory;

    /** The shared "catch-all" specialty whose selection prompts a free-text write-in. */
    public const OTHER_NAME = 'Other (write in)';

    protected $table = 'sp_specialties';

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

    /**
     * Resolve the id of the tenant's "Other (write in)" specialty, or null if absent.
     */
    public static function otherId(?int $tenantId): ?int
    {
        if ($tenantId === null) {
            return null;
        }

        $id = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', self::OTHER_NAME)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'sp_discipline_specialties')
            ->withTimestamps();
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'sp_provider_specialties')
            ->withTimestamps();
    }

    public function intakeRequests(): BelongsToMany
    {
        return $this->belongsToMany(IntakeRequest::class, 'sp_intake_request_specialties')
            ->withTimestamps();
    }
}
