<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderAvailability;
use App\Models\StaffPick\Specialty;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffPick\ProviderProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\FeatureTest;

class ProviderProfileServiceTest extends FeatureTest
{
    private Tenant $tenant;

    private User $clinician;

    private Discipline $discipline;

    /** @var array<int> */
    private array $specialtyIds = [];

    /** @var array<int> */
    private array $languageIds = [];

    /** @var array<int> */
    private array $documentTypeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['atlas.microsoft.com/*' => Http::response([
            'features' => [['geometry' => ['coordinates' => [-80.0533670, 26.8205600]]]],
        ])]);

        $this->tenant = $this->createTenant();
        $this->clinician = $this->createUser($this->tenant);
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
        $this->specialtyIds = [
            Specialty::create(['tenant_id' => $this->tenant->id, 'name' => 'Pediatrics'])->id,
            Specialty::create(['tenant_id' => $this->tenant->id, 'name' => 'Geriatrics'])->id,
        ];
        // Languages are a shared (non-tenant) reference table with a unique code;
        // firstOrCreate avoids collisions across the suite's shared database.
        $this->languageIds = [
            Language::firstOrCreate(['code' => 'en'], ['name' => 'English'])->id,
            Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish'])->id,
        ];
        $this->documentTypeIds = [
            CredentialDocumentType::create(['tenant_id' => $this->tenant->id, 'name' => 'State License', 'is_required' => true, 'has_expiry' => true])->id,
            CredentialDocumentType::create(['tenant_id' => $this->tenant->id, 'name' => 'CPR Certification', 'is_required' => true, 'has_expiry' => true])->id,
        ];
    }

    private function service(): ProviderProfileService
    {
        return app(ProviderProfileService::class);
    }

    private function profileData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
            'email' => 'dana.rivera@example.com',
            'phone' => '5615559876',
            'gender' => 'female',
            'address' => '340 US-1',
            'city' => 'North Palm Beach',
            'state' => 'FL',
            'zip' => '33408',
            'latitude' => null,
            'longitude' => null,
            'discipline_id' => $this->discipline->id,
            'years_experience' => 8,
            'specialties' => $this->specialtyIds,
            'languages' => $this->languageIds,
            'radius_preferred_miles' => 15,
            'radius_max_miles' => 25,
            'service_zone_name' => 'North County',
            'service_zone_points' => [
                ['latitude' => 26.90, 'longitude' => -80.10],
                ['latitude' => 26.90, 'longitude' => -80.00],
                ['latitude' => 26.75, 'longitude' => -80.00],
                ['latitude' => 26.75, 'longitude' => -80.10],
            ],
            'availability' => [
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ['day_of_week' => 3, 'start_time' => '08:00', 'end_time' => '12:00'],
            ],
            'credentials' => [
                ['document_type_id' => $this->documentTypeIds[0], 'file_path' => 'credentials/license.pdf', 'document_number' => 'PT-123', 'expires_at' => '2027-01-01'],
                ['document_type_id' => $this->documentTypeIds[1], 'file_path' => 'credentials/cpr.pdf', 'document_number' => null, 'expires_at' => '2026-12-01'],
            ],
        ], $overrides);
    }

    public function test_submit_creates_a_pending_provider_owned_by_the_user_with_geocoded_coordinates(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->assertSame(Provider::STATUS_PENDING, $provider->status);
        $this->assertFalse((bool) $provider->is_active);
        $this->assertSame($this->clinician->id, $provider->user_id);
        $this->assertSame($this->tenant->id, $provider->tenant_id);
        $this->assertSame('Dana', $provider->first_name);
        $this->assertSame(8, $provider->years_experience);
        $this->assertNotNull($provider->submitted_at);
        // Geocoded from the address via the faked Azure Maps response.
        $this->assertSame(26.82056, (float) $provider->latitude);
        $this->assertSame(-80.053367, (float) $provider->longitude);
    }

    public function test_submit_syncs_specialties_and_languages(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->assertEqualsCanonicalizing($this->specialtyIds, $provider->specialties()->pluck('sp_specialties.id')->all());
        $this->assertEqualsCanonicalizing($this->languageIds, $provider->languages()->pluck('sp_languages.id')->all());
    }

    public function test_submit_ignores_forged_language_and_cross_tenant_specialty_ids(): void
    {
        // The wizard is a Livewire payload an attacker can forge — unknown language IDs
        // and another tenant's specialty IDs must never be synced onto the provider.
        $otherTenant = $this->createTenant();
        $foreignSpecialty = Specialty::create(['tenant_id' => $otherTenant->id, 'name' => 'Foreign-only', 'is_active' => true]);

        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData([
            'languages' => array_merge($this->languageIds, [999999]),
            'specialties' => array_merge($this->specialtyIds, [$foreignSpecialty->id]),
        ]));

        $this->assertEqualsCanonicalizing($this->languageIds, $provider->languages()->pluck('sp_languages.id')->all());
        $this->assertEqualsCanonicalizing($this->specialtyIds, $provider->specialties()->pluck('sp_specialties.id')->all());
    }

    public function test_submit_records_availability_windows(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->assertSame(2, $provider->availability()->count());
        $this->assertDatabaseHas('sp_provider_availability', [
            'provider_id' => $provider->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_submit_builds_a_service_zone_polygon_and_bounding_box(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $zone = $provider->serviceZones()->first();
        $this->assertNotNull($zone);
        $this->assertSame('North County', $zone->name);
        $this->assertStringContainsString('Polygon', $zone->polygon_geojson);
        $this->assertSame(26.9, (float) $zone->bbox_north);
        $this->assertSame(26.75, (float) $zone->bbox_south);
        $this->assertSame(-80.0, (float) $zone->bbox_east);
        $this->assertSame(-80.1, (float) $zone->bbox_west);
    }

    public function test_submit_stores_a_credential_per_document_type(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->assertSame(2, $provider->credentials()->count());
        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $this->documentTypeIds[0],
            'file_path' => 'credentials/license.pdf',
            'document_number' => 'PT-123',
        ]);
    }

    public function test_resubmitting_updates_the_same_profile_without_duplicating_children(): void
    {
        // Null expiry: re-reading a populated SQL Server date column via the local
        // FreeTDS driver yields an unparseable format (see staffpick-sqlserver-testing
        // memory). Railway's real pdo_sqlsrv is fine; the write path is covered above.
        $credentials = [
            ['document_type_id' => $this->documentTypeIds[0], 'file_path' => 'credentials/license.pdf', 'document_number' => 'PT-123', 'expires_at' => null],
        ];

        $first = $this->service()->submit($this->tenant, $this->clinician, $this->profileData(['credentials' => $credentials]));
        $second = $this->service()->submit($this->tenant, $this->clinician, $this->profileData([
            'first_name' => 'Danielle',
            'credentials' => $credentials,
            'availability' => [['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '14:00']],
        ]));

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Danielle', $second->fresh()->first_name);
        $this->assertSame(1, ProviderAvailability::where('provider_id', $first->id)->count());
    }

    public function test_submit_notifies_other_tenant_users_but_not_the_submitter(): void
    {
        $reviewer = $this->createUser($this->tenant);

        $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->assertTrue(DB::table('notifications')->where('notifiable_id', $reviewer->id)->exists());
        $this->assertFalse(DB::table('notifications')->where('notifiable_id', $this->clinician->id)->exists());
    }

    public function test_approve_activates_the_provider(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->service()->approve($provider);

        $this->assertSame(Provider::STATUS_ACTIVE, $provider->fresh()->status);
        $this->assertTrue((bool) $provider->fresh()->is_active);
    }

    public function test_reject_records_the_reason_and_deactivates(): void
    {
        $provider = $this->service()->submit($this->tenant, $this->clinician, $this->profileData());

        $this->service()->reject($provider, 'Incomplete license documentation.');

        $fresh = $provider->fresh();
        $this->assertSame(Provider::STATUS_REJECTED, $fresh->status);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertSame('Incomplete license documentation.', $fresh->rejection_reason);
    }
}
