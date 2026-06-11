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

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '26.8205600', 'lon' => '-80.0533670'],
        ])]);

        $this->tenant = $this->createTenant();
        $this->actingAs($this->createUser($this->tenant));
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);
    }

    public function test_the_wizard_page_renders_for_a_tenant_user(): void
    {
        Livewire::test(ProviderProfile::class)
            ->assertSuccessful()
            ->assertSee('Personal Information')
            ->assertSee('Credentials');
    }

    public function test_submitting_the_wizard_creates_a_pending_provider(): void
    {
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
