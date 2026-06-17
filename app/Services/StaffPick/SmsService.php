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
            Log::warning('Pingram SMS skipped: no API key configured.', ['to' => $this->maskPhone($to)]);

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
                    'to' => $this->maskPhone($to),
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Pingram SMS send threw.', ['to' => $this->maskPhone($to), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Mask all but the last 4 digits so logs never carry a full phone number (PHI). */
    private function maskPhone(string $phone): string
    {
        return str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4);
    }
}
