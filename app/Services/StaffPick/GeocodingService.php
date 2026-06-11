<?php

namespace App\Services\StaffPick;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Forward-geocodes a free-text address to latitude/longitude using the free
 * OpenStreetMap Nominatim service (no API key). Nominatim's usage policy requires
 * an identifying User-Agent and a low request rate — fine for on-save geocoding.
 */
class GeocodingService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name', 'StaffPick').' geocoder ('.config('app.url').')',
            ])
                ->timeout(10)
                ->get(self::ENDPOINT, [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($response->failed()) {
                return null;
            }

            $match = $response->json('0');

            if (! is_array($match) || ! isset($match['lat'], $match['lon'])) {
                return null;
            }

            return [
                'lat' => (float) $match['lat'],
                'lng' => (float) $match['lon'],
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
