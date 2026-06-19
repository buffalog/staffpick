<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\ProviderProfile;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Specialty;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ProviderProfilePageTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->actingAs($this->createUser($this->tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);
    }

    private function fakeGeocodeSuccess(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '26.8205600', 'lon' => '-80.0533670'],
        ])]);
    }

    public function test_the_wizard_page_renders_for_a_tenant_user(): void
    {
        Livewire::test(ProviderProfile::class)
            ->assertSuccessful()
            ->assertSee('Personal Information')
            ->assertSee('Credentials');
    }

    public function test_a_non_admin_clinician_sees_the_nav_even_before_onboarding(): void
    {
        // setUp acts as a non-admin tenant member with no provider record yet —
        // they must still see the page (it's the onboarding wizard).
        $this->assertTrue(ProviderProfile::shouldRegisterNavigation());
        $this->assertTrue(ProviderProfile::canAccess());
    }

    public function test_the_nav_is_hidden_from_an_admin_only_user_without_a_provider_record(): void
    {
        $this->actingAs($this->createTenantAdmin($this->tenant));

        $this->assertFalse(ProviderProfile::shouldRegisterNavigation());
        $this->assertFalse(ProviderProfile::canAccess());
    }

    public function test_an_admin_who_is_also_a_clinician_sees_the_nav(): void
    {
        $admin = $this->createTenantAdmin($this->tenant);
        $this->actingAs($admin);

        Provider::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $admin->id,
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        $this->assertTrue(ProviderProfile::shouldRegisterNavigation());
        $this->assertTrue(ProviderProfile::canAccess());
    }

    public function test_the_wizard_renders_the_interactive_maps(): void
    {
        Livewire::test(ProviderProfile::class)
            ->assertSee('data-sp-leaflet="marker"', false)
            ->assertSee('data-sp-leaflet="polygon"', false);
    }

    public function test_step_one_offers_an_explicit_geocode_button(): void
    {
        // Livewire 4 wire:model.blur doesn't round-trip, so an explicit button is
        // the reliable mid-step geocode trigger.
        Livewire::test(ProviderProfile::class)
            ->assertSee('Find address on map');
    }

    public function test_an_existing_service_zone_prefills_the_polygon_points(): void
    {
        $provider = Provider::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => auth()->id(),
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
            'status' => Provider::STATUS_PENDING,
            'is_active' => false,
        ]);

        // Closed ring (first point repeated last), as the service stores it.
        $provider->serviceZones()->create([
            'name' => 'North County',
            'polygon_geojson' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[
                    [-80.10, 26.90],
                    [-80.00, 26.90],
                    [-80.00, 26.75],
                    [-80.10, 26.90],
                ]],
            ]),
            'bbox_north' => 26.90,
            'bbox_south' => 26.75,
            'bbox_east' => -80.00,
            'bbox_west' => -80.10,
            'is_active' => true,
        ]);

        Livewire::test(ProviderProfile::class)
            ->assertSet('data.service_zone_name', 'North County')
            ->assertSet('data.service_zone_points', [
                ['latitude' => 26.90, 'longitude' => -80.10],
                ['latitude' => 26.90, 'longitude' => -80.00],
                ['latitude' => 26.75, 'longitude' => -80.00],
            ]);
    }

    public function test_a_failed_geocode_flags_the_address_warning_without_setting_coordinates(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]); // no match

        Livewire::test(ProviderProfile::class)
            ->set('data.address', '999 Nonexistent Road')
            ->set('data.city', 'Nowhere')
            ->set('data.state', 'ZZ')
            ->assertSet('data.geocode_failed', true)
            ->assertSet('data.latitude', null);
    }

    public function test_a_successful_geocode_clears_the_warning(): void
    {
        $this->fakeGeocodeSuccess();

        // geocode_failed only becomes false when a lookup actually ran and resolved.
        Livewire::test(ProviderProfile::class)
            ->set('data.address', '340 US-1')
            ->set('data.city', 'North Palm Beach')
            ->set('data.state', 'FL')
            ->assertSet('data.geocode_failed', false)
            ->assertSet('data.latitude', 26.82056);
    }

    public function test_submitting_the_wizard_creates_a_pending_provider(): void
    {
        $this->fakeGeocodeSuccess();
        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);

        Livewire::test(ProviderProfile::class)
            ->set('data', [
                'first_name' => 'Dana',
                'last_name' => 'Rivera',
                'email' => 'dana@example.com',
                'phone' => '5615559876',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'zip' => '33408',
                'discipline_id' => $discipline->id,
                'years_experience' => 8,
                'radius_preferred_miles' => 15,
                'radius_max_miles' => 25,
                'availability' => [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
                'confirm' => true,
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $provider = Provider::where('tenant_id', $this->tenant->id)->where('user_id', auth()->id())->first();
        $this->assertNotNull($provider);
        $this->assertSame(Provider::STATUS_PENDING, $provider->status);
        $this->assertSame('Dana', $provider->first_name);
    }

    public function test_auto_save_creates_a_draft_provider_on_first_change(): void
    {
        Livewire::test(ProviderProfile::class)
            ->set('data.first_name', 'Dana')
            ->set('data.last_name', 'Rivera')
            ->call('autoSave', 1)
            ->assertHasNoErrors();

        $provider = Provider::where('tenant_id', $this->tenant->id)->where('user_id', auth()->id())->first();
        $this->assertNotNull($provider);
        $this->assertSame(Provider::STATUS_DRAFT, $provider->status);
        $this->assertFalse($provider->is_active);
        $this->assertSame('Dana', $provider->first_name);
        $this->assertSame(1, $provider->onboarding_step);
        $this->assertNull($provider->submitted_at);
    }

    public function test_a_credential_upload_persists_immediately_via_auto_save(): void
    {
        $type = CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'State License',
            'is_required' => true,
        ]);

        Livewire::test(ProviderProfile::class)
            ->set('data.first_name', 'Dana')
            ->set("data.credentials.{$type->id}.file_path", 'staffpick/credentials/license.pdf')
            ->call('autoSave', 5)
            ->assertHasNoErrors();

        $provider = Provider::where('tenant_id', $this->tenant->id)->where('user_id', auth()->id())->first();
        $this->assertNotNull($provider);
        $this->assertDatabaseHas('sp_provider_credentials', [
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'file_path' => 'staffpick/credentials/license.pdf',
        ]);
    }

    public function test_returning_resumes_at_the_saved_step_with_prefilled_data(): void
    {
        Provider::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => auth()->id(),
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
            'status' => Provider::STATUS_DRAFT,
            'is_active' => false,
            'onboarding_step' => 4,
        ]);

        Livewire::test(ProviderProfile::class)
            ->assertSet('resumeStep', 4)
            ->assertSet('data.first_name', 'Dana')
            ->assertSet('data.last_name', 'Rivera');
    }

    public function test_submitting_transitions_an_existing_draft_to_pending(): void
    {
        $this->fakeGeocodeSuccess();
        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);

        Provider::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => auth()->id(),
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
            'status' => Provider::STATUS_DRAFT,
            'is_active' => false,
            'onboarding_step' => 2,
        ]);

        Livewire::test(ProviderProfile::class)
            ->set('data', [
                'first_name' => 'Dana',
                'last_name' => 'Rivera',
                'email' => 'dana@example.com',
                'phone' => '5615559876',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'zip' => '33408',
                'discipline_id' => $discipline->id,
                'years_experience' => 8,
                'radius_preferred_miles' => 15,
                'radius_max_miles' => 25,
                'availability' => [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
                'confirm' => true,
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, Provider::where('tenant_id', $this->tenant->id)->where('user_id', auth()->id())->count());

        $provider = Provider::where('tenant_id', $this->tenant->id)->where('user_id', auth()->id())->first();
        $this->assertSame(Provider::STATUS_PENDING, $provider->status);
    }

    public function test_selecting_other_specialty_persists_and_restores_the_write_in_note(): void
    {
        $this->fakeGeocodeSuccess();

        $discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy', 'abbreviation' => 'PT']);
        $ortho = Specialty::create(['tenant_id' => $this->tenant->id, 'name' => 'Orthopaedics']);
        $other = Specialty::create(['tenant_id' => $this->tenant->id, 'name' => Specialty::OTHER_NAME]);
        $discipline->specialties()->attach([$ortho->id, $other->id]);

        Livewire::test(ProviderProfile::class)
            ->set('data', [
                'first_name' => 'Dana',
                'last_name' => 'Rivera',
                'email' => 'dana@example.com',
                'phone' => '5615559876',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'zip' => '33408',
                'discipline_id' => $discipline->id,
                'specialties' => [$ortho->id, $other->id],
                'specialty_other_note' => 'Aquatic Therapy',
                'years_experience' => 8,
                'radius_preferred_miles' => 15,
                'radius_max_miles' => 25,
                'availability' => [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
                'confirm' => true,
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $provider = Provider::where('tenant_id', $this->tenant->id)->where('user_id', auth()->id())->firstOrFail();

        // The write-in lands on the Other pivot only.
        $this->assertSame('Aquatic Therapy', $provider->specialties()->where('sp_specialties.id', $other->id)->first()->pivot->notes);
        $this->assertNull($provider->specialties()->where('sp_specialties.id', $ortho->id)->first()->pivot->notes);

        // A return visit restores the write-in into form state.
        Livewire::test(ProviderProfile::class)
            ->assertSet('data.specialty_other_note', 'Aquatic Therapy');
    }
}
