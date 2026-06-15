<?php

namespace Tests\Feature\StaffPick;

use App\Filament\Dashboard\Pages\ProviderProfile;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
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

    public function test_the_wizard_renders_the_interactive_maps(): void
    {
        Livewire::test(ProviderProfile::class)
            ->assertSee('data-sp-leaflet="marker"', false)
            ->assertSee('data-sp-leaflet="polygon"', false);
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
}
