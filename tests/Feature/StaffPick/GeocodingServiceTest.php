<?php

namespace Tests\Feature\StaffPick;

use App\Services\StaffPick\GeocodingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The geocoder handles PHI (patient/provider addresses). These tests hold the fail-closed
 * contract: a non-BAA endpoint (the old Nominatim leak) must NEVER be called, the default
 * 'none' driver makes no external call at all, and only the covered 'azure' driver reaches out.
 */
class GeocodingServiceTest extends TestCase
{
    private const PHI_ADDRESS = '11154 NW Fernbrook Drive, Port Saint Lucie, FL 34987';

    private function service(): GeocodingService
    {
        return app(GeocodingService::class);
    }

    /** @return array<string, mixed> Azure Maps GeoJSON with [longitude, latitude]. */
    private function azureResponse(float $lng, float $lat): array
    {
        return ['features' => [['geometry' => ['coordinates' => [$lng, $lat]]]]];
    }

    public function test_azure_driver_hits_azure_and_parses(): void
    {
        Config::set('services.geocoding.driver', 'azure');
        Config::set('services.geocoding.azure_maps_key', 'test-key');
        Http::fake(['atlas.microsoft.com/*' => Http::response($this->azureResponse(-80.053367, 26.82056))]);

        $result = $this->service()->geocode('340 US-1, North Palm Beach, FL 33408');

        $this->assertSame(26.82056, $result['lat']);
        $this->assertSame(-80.053367, $result['lng']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'atlas.microsoft.com'));
    }

    public function test_none_driver_makes_no_external_call(): void
    {
        Config::set('services.geocoding.driver', 'none');
        Http::fake();

        $this->assertNull($this->service()->geocode(self::PHI_ADDRESS));
        Http::assertNothingSent();
    }

    /**
     * The regression that makes "green" mean something for PHI: under EITHER driver, the old
     * non-BAA geocoder must never be contacted. If this ever fails, patient addresses are
     * leaving to an uncovered vendor again.
     */
    public function test_geocoder_never_calls_a_non_baa_endpoint(): void
    {
        Http::fake();

        foreach (['none', 'azure'] as $driver) {
            Config::set('services.geocoding.driver', $driver);
            Config::set('services.geocoding.azure_maps_key', 'test-key');
            $this->service()->geocode(self::PHI_ADDRESS);
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openstreetmap')
            || str_contains($request->url(), 'nominatim'));
    }

    public function test_it_returns_null_when_no_result_is_found(): void
    {
        Config::set('services.geocoding.driver', 'azure');
        Config::set('services.geocoding.azure_maps_key', 'test-key');
        Http::fake(['atlas.microsoft.com/*' => Http::response(['features' => []])]);

        $this->assertNull($this->service()->geocode('nowhere at all'));
    }

    public function test_it_returns_null_on_a_failed_request(): void
    {
        Config::set('services.geocoding.driver', 'azure');
        Config::set('services.geocoding.azure_maps_key', 'test-key');
        Http::fake(['atlas.microsoft.com/*' => Http::response('error', 500)]);

        $this->assertNull($this->service()->geocode('anywhere'));
    }

    public function test_it_returns_null_for_a_blank_address(): void
    {
        Config::set('services.geocoding.driver', 'azure');
        Http::fake();

        $this->assertNull($this->service()->geocode('   '));
        Http::assertNothingSent();
    }

    public function test_azure_driver_without_a_key_makes_no_call(): void
    {
        Config::set('services.geocoding.driver', 'azure');
        Config::set('services.geocoding.azure_maps_key', null);
        Http::fake();

        $this->assertNull($this->service()->geocode(self::PHI_ADDRESS));
        Http::assertNothingSent();
    }
}
