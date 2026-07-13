<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReferralSource extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'sp_referral_sources';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'contact_name',
        'address',
        'city',
        'state',
        'zip',
        'phone',
        'fax',
        'email',
        'portal_username',
        'intake_token',
        'status',
        'billing_terms_days',
        'group_id',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'group_id' => 'integer',
            'user_id' => 'integer',
            'billing_terms_days' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReferralSourceGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intakeRequests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Single source of truth for rejection reasons, shared by the Filament reject
     * action (Select options) and the rejection email (label lookup).
     *
     * @return array<string, string>
     */
    public static function rejectionReasonOptions(): array
    {
        return [
            'duplicate' => __('Duplicate — already registered under another record'),
            'out_of_service_area' => __('Out of service area'),
            'unable_to_verify' => __('Unable to verify agency'),
            'incomplete_information' => __('Incomplete information'),
            'not_accepting' => __('Not accepting new referral sources'),
            'other' => __('Other'),
        ];
    }

    /**
     * Mint the source's public intake token if it doesn't have one yet, and
     * return it. Uniqueness is enforced here (the DB index is a Railway-only
     * backstop — see the migration); collisions are astronomically unlikely with
     * 32 url-safe chars but we re-roll on the off chance.
     */
    public function ensureIntakeToken(): string
    {
        if (filled($this->intake_token)) {
            return $this->intake_token;
        }

        do {
            $token = Str::random(32);
        } while (static::query()->where('intake_token', $token)->exists());

        $this->forceFill(['intake_token' => $token])->save();

        return $token;
    }

    public function getIntakeUrl(): string
    {
        return route('staffpick.intake.show', ['token' => $this->ensureIntakeToken()]);
    }
}
