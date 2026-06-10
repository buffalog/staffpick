<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'sp_subjects';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_alt',
        'alt_contact_name',
        'alt_contact_phone',
        'alt_contact_relationship',
        'address',
        'address_2',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'date_of_birth',
        'gender',
        'preferred_language',
        'diagnosis',
        'pcp_name',
        'pcp_phone',
        'insurance_type_id',
        'insurance_id',
        'insurance_group',
        'provider_gender_preference',
        'language_preference',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function insuranceType(): BelongsTo
    {
        return $this->belongsTo(InsuranceType::class, 'insurance_type_id');
    }

    public function intakeRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }
}
