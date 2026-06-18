<?php

namespace App\Services\StaffPick\Auth;

use App\Models\StaffPick\AuthLog;

/**
 * Writes auth audit rows for any auth event type (success and failure), capturing
 * the request IP automatically. App code currently routes SSO redirect/callback
 * attempts through here.
 */
class AuthLogger
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function success(string $eventType, array $attributes = []): AuthLog
    {
        return $this->write($eventType, array_merge($attributes, ['success' => true]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function failure(string $eventType, string $error, array $attributes = []): AuthLog
    {
        return $this->write($eventType, array_merge($attributes, [
            'success' => false,
            'error_message' => $error,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(string $eventType, array $attributes): AuthLog
    {
        return AuthLog::create(array_merge([
            'event_type' => $eventType,
            'ip_address' => request()?->ip(),
            'success' => false,
        ], $attributes));
    }
}
