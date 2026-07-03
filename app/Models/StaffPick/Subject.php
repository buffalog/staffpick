<?php

namespace App\Models\StaffPick;

use App\Models\StaffPick\Concerns\BelongsToTenant;
use App\Services\StaffPick\GeocodingService;
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

    protected static function booted(): void
    {
        // Geocode the address on save so matching (which resolves location through the
        // subject's lat/lng) always has coordinates — regardless of entry path
        // (dashboard form, inline create-from-intake, public intake, backfill).
        static::saving(function (Subject $subject): void {
            // Only auto-geocode interactive web saves (dashboard form, inline
            // create-from-intake). Console contexts — seeders, queue, tests — either
            // supply coordinates directly or backfill via GeocoordinateSeeder, and must
            // not fire a live Nominatim call per row on every boot.
            // ponytail: runningInConsole() also skips queue workers; no subject-save
            // queue path needs geocoding today. Add a targeted flag if that changes.
            if (app()->runningInConsole()) {
                return;
            }

            $subject->geocodeAddressIfNeeded();
        });
    }

    /**
     * Populate latitude/longitude from the address when they are missing, or when
     * the address changed and no coordinates were supplied this save. Supplied
     * coordinates (manual entry / pin-drop) always win — mirrors
     * IntakeSubmissionService::resolveCoordinates.
     *
     * ponytail: synchronous Nominatim call on save (matches the other on-save
     * geocoders). Move to a queued job if subject write volume ever spikes.
     */
    public function geocodeAddressIfNeeded(): void
    {
        $address = collect([$this->address, $this->city, $this->state, $this->zip])
            ->filter()
            ->implode(', ');

        if ($address === '') {
            return;
        }

        $coordsProvided = filled($this->latitude) && filled($this->longitude);
        $coordsEditedThisSave = $this->isDirty(['latitude', 'longitude']);
        $addressChanged = $this->isDirty(['address', 'city', 'state', 'zip']);

        if ($coordsProvided && ($coordsEditedThisSave || ! $addressChanged)) {
            return;
        }

        $result = app(GeocodingService::class)->geocode($address);

        if ($result !== null) {
            $this->latitude = $result['lat'];
            $this->longitude = $result['lng'];
        }
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
