<?php

namespace Tests\Unit\StaffPick;

use App\Models\StaffPick\Subject;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubjectGeocodingTest extends TestCase
{
    private function fakeNominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '29.0250000', 'lon' => '-80.9048000'],
            ]),
        ]);
    }

    public function test_it_geocodes_address_when_coordinates_are_missing(): void
    {
        $this->fakeNominatim();

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
        $this->fakeNominatim();

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
        $this->fakeNominatim();

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

    public function test_it_does_nothing_without_an_address(): void
    {
        $this->fakeNominatim();

        $subject = new Subject(['first_name' => 'Judd', 'last_name' => 'Kussrow']);

        $subject->geocodeAddressIfNeeded();

        Http::assertNothingSent();
        $this->assertNull($subject->latitude);
        $this->assertNull($subject->longitude);
    }
}
