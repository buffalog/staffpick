<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Models\User;
use App\Services\StaffPick\GeocodingService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
        'can_adjust_own_service_zones',
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
            'can_adjust_own_service_zones' => 'boolean',
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
        // Geocode a provider's address on save so matching always has coordinates.
        // Mirrors the Subject hook: only on an interactive web save (dashboard form /
        // wizard), never in console — imports and seeders supply coordinates directly
        // and must not fire a Nominatim call per row.
        static::saving(function (Provider $provider): void {
            if (app()->runningInConsole()) {
                return;
            }

            $provider->geocodeAddressIfNeeded();
        });
    }

    /**
     * Backend-geocode the address ONLY on a staff-admin edit that changed the address
     * without supplying coordinates. If a lat/long came with this same save — a manual
     * entry or a wizard pin-drop — it wins and geocoding is skipped; a human-placed pin
     * is never overwritten.
     */
    public function geocodeAddressIfNeeded(): void
    {
        // Trigger only when an address field actually changed.
        if (! $this->isDirty(['address', 'city', 'state', 'zip'])) {
            return;
        }

        // Coordinates supplied in this same save win — skip geocoding entirely.
        if ($this->coordinatesSuppliedThisSave()) {
            return;
        }

        $address = collect([$this->address, $this->city, $this->state, $this->zip])
            ->filter()
            ->implode(', ');

        if ($address === '') {
            return;
        }

        $result = app(GeocodingService::class)->geocode($address);

        if ($result !== null) {
            $this->latitude = $result['lat'];
            $this->longitude = $result['lng'];
        }
    }

    /**
     * Whether latitude/longitude were deliberately provided in the current save — a new
     * record that arrived with coordinates, or an existing one whose coordinates changed.
     * Compared numerically on purpose: the decimal:7 cast makes a Filament form echoing
     * the stored value read as dirty, so isDirty() here would false-positive and suppress
     * a legitimate staff address re-geocode (the exact bug seen on Subject).
     */
    private function coordinatesSuppliedThisSave(): bool
    {
        if (blank($this->latitude) || blank($this->longitude)) {
            return false;
        }

        if (! $this->exists) {
            return true;
        }

        $moved = fn (string $column): bool => abs((float) $this->{$column} - (float) $this->getOriginal($column)) > 1e-7;

        return $moved('latitude') || $moved('longitude');
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

    /**
     * The provider's PRIMARY discipline. Kept for back-compat; mirrors the is_primary
     * row of {@see disciplines()}. New code that needs the full set should use
     * disciplines() — a provider can hold more than one (e.g. dual-licensed OT/PT).
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /**
     * Every discipline the provider holds. Matching filters on this set, so a
     * multi-discipline provider is eligible for cases in ANY of their disciplines.
     */
    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'sp_provider_disciplines')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Reconcile the primary discipline after the disciplines pivot has been synced:
     * exactly one pivot row is flagged is_primary and the legacy discipline_id column
     * points at it. Keeps the existing primary when it is still held, otherwise falls
     * back to the first. Call this from every write path AFTER syncing the pivot.
     */
    public function assignPrimaryDiscipline(): void
    {
        $ids = $this->disciplines()->pluck('sp_disciplines.id')->map(fn ($id): int => (int) $id)->all();

        $pivot = $this->disciplines()->newPivotStatement()->where('provider_id', $this->getKey());

        if ($ids === []) {
            $pivot->update(['is_primary' => false]);

            if ($this->discipline_id !== null) {
                $this->updateQuietly(['discipline_id' => null]);
            }

            return;
        }

        $primary = in_array((int) $this->discipline_id, $ids, true) ? (int) $this->discipline_id : $ids[0];

        $pivot->update(['is_primary' => false]);
        $this->disciplines()->updateExistingPivot($primary, ['is_primary' => true]);

        if ((int) $this->discipline_id !== $primary) {
            $this->updateQuietly(['discipline_id' => $primary]);
        }
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

    /**
     * Count of credentials that are expired or within their type's own
     * expiry_warning_days window — the single source of the credential-attention
     * threshold, shared by the compliance sweep, the stats widget, and the detail-page
     * dot. expires_at is a date cast, so it's read via getRawOriginal (dblib-safe).
     */
    public function credentialAlertCount(): int
    {
        $today = now()->startOfDay();

        return $this->credentials()->with('documentType')->get()
            ->filter(function (ProviderCredential $credential) use ($today): bool {
                if ($credential->status === 'expired') {
                    return true;
                }

                $raw = $credential->getRawOriginal('expires_at');

                if (blank($raw)) {
                    return false;
                }

                $warningDays = (int) ($credential->documentType?->expiry_warning_days ?? 0);

                return Carbon::parse($raw)->startOfDay()->lte($today->copy()->addDays($warningDays));
            })
            ->count();
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
