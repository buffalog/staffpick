<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_specialties';

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
