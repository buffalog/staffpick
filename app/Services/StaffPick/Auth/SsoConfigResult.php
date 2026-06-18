<?php

namespace App\Services\StaffPick\Auth;

/**
 * Outcome of an SSO configuration "test" check: a UI-agnostic status the Page maps
 * to a Filament notification. Status is one of success | warning | error.
 */
class SsoConfigResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public static function success(string $title, string $body): self
    {
        return new self('success', $title, $body);
    }

    public static function warning(string $title, string $body): self
    {
        return new self('warning', $title, $body);
    }

    public static function error(string $title, string $body): self
    {
        return new self('error', $title, $body);
    }
}
