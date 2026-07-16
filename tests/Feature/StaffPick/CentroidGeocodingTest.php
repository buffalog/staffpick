<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\ZipCentroid;
use App\Services\StaffPick\GeocodingService;
use App\Services\StaffPick\TenantContext;
use Illuminate\Support\Facades\Http;
use Tests\Feature\FeatureTest;

/**
 * The self-hosted 'centroid' geocoding driver: resolves an address ZIP to a local centroid with
 * NO network and NO egress, so it is PHI-safe with no BAA. Rows are seeded idempotently because
 * the suite shares one never-rolled-back SQL Server DB.
 */
class CentroidGeocodingTest extends FeatureTest
{
    private function seedCentroid(string $zip, float $lat, float $lng, string $source = 'census'): void
    {
        ZipCentroid::updateOrCreate(
            ['zip' => $zip],
            ['latitude' => $lat, 'longitude' => $lng, 'source' => $source],
        );
    }

    private function service(): GeocodingService
    {
        return app(GeocodingService::class);
    }

    public function test_centroid_resolves_an_address_ending_in_a_seeded_zip(): void
    {
        config(['services.geocoding.driver' => 'centroid']);
        $this->seedCentroid('60601', 41.8853000, -87.6216000);

        $result = $this->service()->geocode('233 S Wacker Dr, Chicago, IL 60601');

        $this->assertSame(41.8853, $result['lat']);
        $this->assertSame(-87.6216, $result['lng']);
    }

    public function test_centroid_ignores_a_five_digit_street_number_with_no_zip(): void
    {
        // End-anchor guardrail: the 5-digit street number is NOT the final token, so it must
        // never be read as a ZIP. If someone drops the `$` end-anchor, this test fails.
        config(['services.geocoding.driver' => 'centroid']);
        $this->seedCentroid('12345', 10.0, 20.0);

        $this->assertNull($this->service()->geocode('12345 Main St, Springfield, IL'));
    }

    public function test_centroid_resolves_a_zip_plus_four_tail(): void
    {
        config(['services.geocoding.driver' => 'centroid']);
        $this->seedCentroid('60601', 41.8853000, -87.6216000);

        $result = $this->service()->geocode('233 S Wacker Dr, Chicago, IL 60601-1234');

        $this->assertSame(41.8853, $result['lat']);
        $this->assertSame(-87.6216, $result['lng']);
    }

    public function test_centroid_returns_null_for_an_unknown_zip(): void
    {
        config(['services.geocoding.driver' => 'centroid']);

        // 77099 is never seeded by this test, so the centroid lookup misses.
        $this->assertNull($this->service()->geocode('1 Nowhere Rd, Nowhere, TX 77099'));
    }

    public function test_none_driver_returns_null_even_with_a_valid_zip(): void
    {
        config(['services.geocoding.driver' => 'none']);
        $this->seedCentroid('60601', 41.8853000, -87.6216000);

        // Fail-closed default is intact: a resolvable ZIP still geocodes to nothing.
        $this->assertNull($this->service()->geocode('233 S Wacker Dr, Chicago, IL 60601'));
    }

    public function test_centroid_makes_no_network_call(): void
    {
        config(['services.geocoding.driver' => 'centroid']);
        $this->seedCentroid('60601', 41.8853000, -87.6216000);
        Http::fake();

        $this->service()->geocode('233 S Wacker Dr, Chicago, IL 60601');

        Http::assertNothingSent();
    }

    public function test_centroid_resolves_with_no_tenant_context(): void
    {
        // Global reference table: not tenant-scoped and not a BearsTenantPhi model, so it must
        // resolve with no tenant context and no Filament tenant (proves the H3b read guard and
        // the tenant scope do not catch it).
        config(['services.geocoding.driver' => 'centroid']);
        app(TenantContext::class)->set(null);
        $this->seedCentroid('60601', 41.8853000, -87.6216000);

        $result = $this->service()->geocode('233 S Wacker Dr, Chicago, IL 60601');

        $this->assertSame(41.8853, $result['lat']);
        $this->assertSame(-87.6216, $result['lng']);
    }

    public function test_centroid_resolves_a_geonames_sourced_row(): void
    {
        // The GeoNames gap-fill layer (CC-BY, PO-box/point ZIPs) resolves just like Census rows.
        config(['services.geocoding.driver' => 'centroid']);
        $this->seedCentroid('00501', 40.8154000, -73.0451000, 'geonames');

        $result = $this->service()->geocode('Internal Revenue Service, Holtsville, NY 00501');

        $this->assertSame(40.8154, $result['lat']);
        $this->assertSame(-73.0451, $result['lng']);
        $this->assertSame('geonames', ZipCentroid::find('00501')->source);
    }
}
