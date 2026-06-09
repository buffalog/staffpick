<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'sp_providers';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'business_name',
        'email',
        'phone',
        'phone_alt',
        'address',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'discipline_id',
        'tier_id',
        'office_id',
        'is_contractor',
        'radius_preferred_miles',
        'radius_max_miles',
        'gender',
        'status',
        'is_active',
        'deactivated_at',
        'deactivation_reason',
        'payroll_id',
        'tax_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_contractor' => 'boolean',
            'radius_preferred_miles' => 'integer',
            'radius_max_miles' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(ProviderTier::class, 'tier_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'sp_provider_specialties')
            ->withTimestamps();
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'sp_provider_languages')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function serviceZones(): HasMany
    {
        return $this->hasMany(ProviderServiceZone::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ProviderCredential::class);
    }

    public function availability(): HasMany
    {
        return $this->hasMany(ProviderAvailability::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function assignmentOffers(): HasMany
    {
        return $this->hasMany(AssignmentOffer::class);
    }
}
