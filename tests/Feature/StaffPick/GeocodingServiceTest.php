<?php

namespace Tests\Feature\StaffPick;

use App\Services\StaffPick\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    private function service(): GeocodingService
    {
        return app(GeocodingService::class);
    }

    public function test_it_returns_lat_lng_for_a_resolvable_address(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '26.8205600', 'lon' => '-80.0533670', 'display_name' => 'North Palm Beach, FL'],
            ]),
        ]);

        $result = $this->service()->geocode('340 US-1, North Palm Beach, FL 33408');

        $this->assertSame(26.82056, $result['lat']);
        $this->assertSame(-80.053367, $result['lng']);
    }

    public function test_it_sends_a_user_agent_as_nominatim_requires(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '1', 'lon' => '2']])]);

        $this->service()->geocode('somewhere');

        Http::assertSent(fn ($request) => filled($request->header('User-Agent')[0] ?? null));
    }

    public function test_it_returns_null_when_no_result_is_found(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->assertNull($this->service()->geocode('nowhere at all'));
    }

    public function test_it_returns_null_on_a_failed_request(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('error', 500)]);

        $this->assertNull($this->service()->geocode('anywhere'));
    }

    public function test_it_returns_null_for_a_blank_address(): void
    {
        Http::fake();

        $this->assertNull($this->service()->geocode('   '));
        Http::assertNothingSent();
    }
}
