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
