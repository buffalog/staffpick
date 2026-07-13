<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discipline extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_disciplines';

    protected $fillable = [
        'tenant_id',
        'name',
        'abbreviation',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'sp_discipline_specialties')
            ->withTimestamps();
    }

    public function intakeRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }
}
