<?php

namespace App\Services\StaffPick;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends SMS through Pingram (https://www.pingram.io) via its REST API:
 * POST {base}/sms with a Bearer API key and a JSON body of {type, to, message}.
 *
 * Never throws — an SMS failure should never block the surrounding flow — and logs
 * every failure (missing key, API error, transport exception) for debugging.
 */
class SmsService
{
    public function send(string $to, string $message): bool
    {
        $apiKey = config('services.pingram.api_key');

        if (blank($apiKey)) {
            Log::warning('Pingram SMS skipped: no API key configured.', ['to' => $to]);

            return false;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post(rtrim((string) config('services.pingram.base_url'), '/').'/sms', [
                    'type' => config('services.pingram.sms_type'),
                    'to' => $to,
                    'message' => $message,
                ]);

            if ($response->failed()) {
                Log::warning('Pingram SMS send failed.', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Pingram SMS send threw.', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
