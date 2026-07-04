<?php

namespace Tests\Unit\StaffPick;

use App\Models\StaffPick\Provider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderGeocodingTest extends TestCase
{
    private function fakeNominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '29.0250000', 'lon' => '-80.9048000'],
            ]),
        ]);
    }

    private function existing(array $attributes): Provider
    {
        $provider = new Provider($attributes);
        $provider->exists = true;
        $provider->syncOriginal();

        return $provider;
    }

    public function test_geocodes_a_new_record_with_an_address_and_no_coordinates(): void
    {
        $this->fakeNominatim();

        $provider = new Provider(['address' => '108 North Peninsula', 'city' => 'New Smyrna Beach', 'state' => 'FL', 'zip' => '32169']);
        $provider->geocodeAddressIfNeeded();

        $this->assertSame(29.025, (float) $provider->latitude);
        $this->assertSame(-80.9048, (float) $provider->longitude);
    }

    public function test_supplied_coordinates_on_create_win(): void
    {
        $this->fakeNominatim();

        // Wizard pin-drop / CSV import: coords arrive with the record → never geocode.
        $provider = new Provider(['address' => '108 North Peninsula', 'city' => 'New Smyrna Beach', 'state' => 'FL', 'zip' => '32169', 'latitude' => 12.34, 'longitude' => 56.78]);
        $provider->geocodeAddressIfNeeded();

        Http::assertNothingSent();
        $this->assertSame(12.34, (float) $provider->latitude);
        $this->assertSame(56.78, (float) $provider->longitude);
    }

    public function test_staff_address_edit_without_new_coords_regeocodes(): void
    {
        $this->fakeNominatim();

        $provider = $this->existing(['address' => '1 Old St', 'city' => 'Orlando', 'state' => 'FL', 'zip' => '32801', 'latitude' => 28.5, 'longitude' => -81.3]);
        // Staff change the address; the form echoes the stored coords unchanged.
        $provider->address = '108 North Peninsula';
        $provider->city = 'New Smyrna Beach';
        $provider->zip = '32169';

        $provider->geocodeAddressIfNeeded();

        $this->assertSame(29.025, (float) $provider->latitude);
        $this->assertSame(-80.9048, (float) $provider->longitude);
    }

    public function test_manually_moved_pin_on_an_address_edit_is_not_overwritten(): void
    {
        $this->fakeNominatim();

        $provider = $this->existing(['address' => '1 Old St', 'city' => 'Orlando', 'state' => 'FL', 'zip' => '32801', 'latitude' => 28.5, 'longitude' => -81.3]);
        // Address changed AND a new pin was placed in the same edit.
        $provider->address = '108 North Peninsula';
        $provider->latitude = 29.9;
        $provider->longitude = -80.5;

        $provider->geocodeAddressIfNeeded();

        Http::assertNothingSent();
        $this->assertSame(29.9, (float) $provider->latitude);
        $this->assertSame(-80.5, (float) $provider->longitude);
    }

    public function test_unrelated_field_change_does_not_geocode(): void
    {
        $this->fakeNominatim();

        $provider = $this->existing(['address' => '1 Old St', 'city' => 'Orlando', 'state' => 'FL', 'zip' => '32801', 'phone' => '5550000000']);
        $provider->phone = '5551234567';

        $provider->geocodeAddressIfNeeded();

        Http::assertNothingSent();
    }
}
