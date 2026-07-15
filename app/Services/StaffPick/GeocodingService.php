<?php

namespace App\Services\StaffPick;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Forward-geocodes a free-text address (PHI — it's a patient or provider address) to
 * latitude/longitude.
 *
 * FAIL CLOSED. The previous implementation posted every address to a public geocoder with
 * no HIPAA BAA on every subject and provider save — a PHI egress. There is deliberately no
 * public/free fallback here: with no covered driver configured (the default), this geocodes
 * NOTHING rather than leak. A driver is opt-in and must sit behind a signed BAA. The single
 * public method and return shape are unchanged, so the seven callers need no changes.
 *
 * @see config/services.php 'geocoding' — GEOCODING_DRIVER defaults to 'none'.
 */
class GeocodingService
{
    private const AZURE_ENDPOINT = 'https://atlas.microsoft.com/geocode';

    private const AZURE_API_VERSION = '2023-06-01';

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        return match (config('services.geocoding.driver')) {
            'azure' => $this->geocodeAzureMaps($address),
            // 'none' / unset = fail closed: no external call, no egress, no coordinates.
            default => null,
        };
    }

    /**
     * Azure Maps Get Geocoding (covered under an Azure HIPAA BAA when the account is scoped
     * for it). Returns the first match's coordinates, or null on any failure. The address is
     * never logged — preserves the prior no-logging behaviour and keeps PHI out of app logs.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function geocodeAzureMaps(string $address): ?array
    {
        $key = config('services.geocoding.azure_maps_key');

        if (blank($key)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get(self::AZURE_ENDPOINT, [
                'api-version' => self::AZURE_API_VERSION,
                'subscription-key' => $key,
                'query' => $address,
                'top' => 1,
            ]);

            if ($response->failed()) {
                return null;
            }

            // GeoJSON: features[0].geometry.coordinates is [longitude, latitude].
            $coordinates = $response->json('features.0.geometry.coordinates');

            if (! is_array($coordinates) || ! isset($coordinates[0], $coordinates[1])) {
                return null;
            }

            return [
                'lat' => (float) $coordinates[1],
                'lng' => (float) $coordinates[0],
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
