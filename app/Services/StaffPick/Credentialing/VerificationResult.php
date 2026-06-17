<?php

namespace App\Services\StaffPick\Credentialing;

use Carbon\CarbonInterface;

/**
 * The outcome of attempting to verify a provider credential. For the API path it
 * carries the mapped status + parsed details + raw response; for the deep-link path
 * it carries the pre-filled board URL the staffer should open; for manual it's inert.
 */
final class VerificationResult
{
    /**
     * @param  array<int, mixed>|null  $disciplinaryActions
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public readonly string $status,
        public readonly string $source,
        public readonly ?CarbonInterface $verifiedAt = null,
        public readonly ?string $expirationDate = null,
        public readonly ?string $nameOnLicense = null,
        public readonly ?array $disciplinaryActions = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $deepLinkUrl = null,
        public readonly ?array $rawResponse = null,
    ) {}
}
