<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Provider extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_REJECTED = 'rejected';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_PORTAL = 'portal';

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
        'internal_rating',
        'is_preferred',
        'rating_90day_avg',
        'rating_180day_avg',
        'rating_survey_count_90day',
        'rating_survey_count_180day',
        'user_id',
        'years_experience',
        'rejection_reason',
        'submitted_at',
        'onboarding_step',
        'preferred_contact_channel',
        'calendar_token',
        'calendar_token_generated_at',
        'color',
        'photo',
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
            'internal_rating' => 'decimal:2',
            'is_preferred' => 'boolean',
            'rating_90day_avg' => 'decimal:2',
            'rating_180day_avg' => 'decimal:2',
            'rating_survey_count_90day' => 'integer',
            'rating_survey_count_180day' => 'integer',
            'years_experience' => 'integer',
            'submitted_at' => 'datetime',
            'onboarding_step' => 'integer',
            'calendar_token_generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Auto-assign an identity color on creation (any path: Filament, services,
        // factory) unless one was set explicitly. Registered after BelongsToTenant's
        // creating hook, so tenant_id is already populated here.
        static::creating(function (Provider $provider): void {
            if ($provider->color === null) {
                $provider->color = static::nextIdentityColor($provider->tenant_id);
            }
        });
    }

    /**
     * Next golden-angle identity color for a tenant — evenly spaced hues that don't
     * cluster, indexed by the tenant's current provider count.
     *
     * ponytail: index is a live count, so concurrent creates or later deletes can
     * repeat a hue. Cosmetic only (colors aren't unique); staff can override.
     */
    private static function nextIdentityColor(?int $tenantId): string
    {
        $index = $tenantId === null
            ? 0
            : static::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

        return static::hslToHex(fmod($index * 137.508, 360), 65, 48);
    }

    /**
     * Convert HSL (h 0–360, s/l 0–100) to a #RRGGBB hex string.
     */
    public static function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf(
            '#%02X%02X%02X',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }

    /**
     * Convert a #RRGGBB (or #RGB) hex string to an `rgba(r, g, b, opacity)` CSS value
     * for inline background tints. Falls back to a neutral slate on malformed input.
     */
    public static function hexToRgba(string $hex, float $opacity = 1.0): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return "rgba(100, 116, 139, {$opacity})";
        }

        return sprintf(
            'rgba(%d, %d, %d, %s)',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            $opacity,
        );
    }

    /** Display name — "First Last", trimmed. Backs $provider->full_name everywhere. */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Issue (or re-issue) the provider's iCal feed token and return it. Any previously
     * issued token stops working immediately.
     */
    public function generateCalendarToken(): string
    {
        $token = Str::random(48);

        $this->update([
            'calendar_token' => $token,
            'calendar_token_generated_at' => now(),
        ]);

        return $token;
    }

    public function revokeCalendarToken(): void
    {
        $this->update([
            'calendar_token' => null,
            'calendar_token_generated_at' => null,
        ]);
    }

    /** Absolute URL of this provider's public iCal feed, or null when no token is set. */
    public function calendarFeedUrl(): ?string
    {
        if ($this->calendar_token === null) {
            return null;
        }

        return route('staffpick.calendar.feed', [
            'tenantIdentifier' => $this->tenant?->uuid,
            'token' => $this->calendar_token,
        ]);
    }

    /**
     * Normalize the address state to a trimmed, uppercase code on write. The license
     * verification RapidAPI call sends this value verbatim, so keep it clean regardless
     * of the input path (wizard Select, admin form, factory, import).
     */
    protected function state(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? strtoupper(trim($value)) : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            ->withPivot('notes')
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

    public function surveys(): HasMany
    {
        return $this->hasMany(ProviderSurvey::class);
    }

    public function ratingReviews(): HasMany
    {
        return $this->hasMany(ProviderRatingReview::class);
    }

    /**
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
}
