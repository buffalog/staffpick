<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralSource extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sp_referral_sources';

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'city',
        'state',
        'zip',
        'phone',
        'fax',
        'email',
        'portal_username',
        'status',
        'billing_terms_days',
        'group_id',
    ];

    protected function casts(): array
    {
        return [
            'billing_terms_days' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReferralSourceGroup::class, 'group_id');
    }

    public function intakeRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }
}
