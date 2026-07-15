<?php

namespace Tests\Unit\StaffPick;

use App\Models\StaffPick\Subject;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubjectGeocodingTest extends TestCase
{
    private function fakeGeocoder(): void
    {
        // Azure Maps GeoJSON: features[0].geometry.coordinates is [longitude, latitude].
        Http::fake([
            'atlas.microsoft.com/*' => Http::response([
                'features' => [['geometry' => ['coordinates' => [-80.9048000, 29.0250000]]]],
            ]),
        ]);
    }

    public function test_it_geocodes_address_when_coordinates_are_missing(): void
    {
        $this->fakeGeocoder();

        $subject = new Subject([
            'address' => '108 North Peninsula',
            'city' => 'New Smyrna Beach',
            'state' => 'FL',
            'zip' => '32169',
        ]);

        $subject->geocodeAddressIfNeeded();

        $this->assertSame(29.025, (float) $subject->latitude);
        $this->assertSame(-80.9048, (float) $subject->longitude);
    }

    public function test_it_respects_supplied_coordinates_on_create(): void
    {
        $this->fakeGeocoder();

        // A new record (exists = false) that already carries coordinates — e.g. a
        // public-intake pin-drop — must be left untouched.
        $subject = new Subject([
            'address' => '108 North Peninsula',
            'city' => 'New Smyrna Beach',
            'state' => 'FL',
            'zip' => '32169',
            'latitude' => 12.34,
            'longitude' => 56.78,
        ]);

        $subject->geocodeAddressIfNeeded();

        Http::assertNothingSent();
        $this->assertSame(12.34, (float) $subject->latitude);
        $this->assertSame(56.78, (float) $subject->longitude);
    }

    public function test_it_regeocodes_when_an_existing_records_address_changes(): void
    {
        $this->fakeGeocoder();

        // Simulate an existing, already-geocoded record.
        $subject = new Subject([
            'address' => '1 Old Street',
            'city' => 'Orlando',
            'state' => 'FL',
            'zip' => '32801',
            'latitude' => 28.5383,
            'longitude' => -81.3792,
        ]);
        $subject->exists = true;
        $subject->syncOriginal();

        // A scheduler corrects the address.
        $subject->address = '108 North Peninsula';
        $subject->city = 'New Smyrna Beach';
        $subject->zip = '32169';

        $subject->geocodeAddressIfNeeded();

        // Coordinates must follow the new address, not stay pinned to the old ones.
        $this->assertSame(29.025, (float) $subject->latitude);
        $this->assertSame(-80.9048, (float) $subject->longitude);
    }

    public function test_it_regeocodes_when_only_the_state_field_changes(): void
    {
        // Proves the address-change check covers all four fields, not just street:
        // a state-only edit on an existing geocoded record still re-geocodes.
        $this->fakeGeocoder();

        $subject = new Subject([
            'address' => '108 North Peninsula',
            'city' => 'New Smyrna Beach',
            'state' => 'GA',
            'zip' => '32169',
            'latitude' => 10.0,
            'longitude' => 20.0,
        ]);
        $subject->exists = true;
        $subject->syncOriginal();

        $subject->state = 'FL';

        $subject->geocodeAddressIfNeeded();

        $this->assertSame(29.025, (float) $subject->latitude);
        $this->assertSame(-80.9048, (float) $subject->longitude);
    }

    public function test_it_does_not_regeocode_when_only_an_unrelated_field_changes(): void
    {
        // A phone/contact edit on an already-geocoded record must NOT re-geocode:
        // no wasted geocoder call, and the coordinates stay put.
        $this->fakeGeocoder();

        $subject = new Subject([
            'address' => '108 North Peninsula',
            'city' => 'New Smyrna Beach',
            'state' => 'FL',
            'zip' => '32169',
            'latitude' => 29.025,
            'longitude' => -80.9048,
            'phone' => '5550000000',
        ]);
        $subject->exists = true;
        $subject->syncOriginal();

        $subject->phone = '5551234567';

        $subject->geocodeAddressIfNeeded();

        Http::assertNothingSent();
        $this->assertSame(29.025, (float) $subject->latitude);
        $this->assertSame(-80.9048, (float) $subject->longitude);
    }

    public function test_it_does_nothing_without_an_address(): void
    {
        $this->fakeGeocoder();

        $subject = new Subject(['first_name' => 'Judd', 'last_name' => 'Kussrow']);

        $subject->geocodeAddressIfNeeded();

        Http::assertNothingSent();
        $this->assertNull($subject->latitude);
        $this->assertNull($subject->longitude);
    }
}
