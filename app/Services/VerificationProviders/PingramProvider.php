<?php

namespace App\Services\VerificationProviders;

use App\Constants\VerificationProviderConstants;
use App\Services\StaffPick\SmsService;

/**
 * Phone-verification provider backed by Pingram. The verification framework
 * generates the one-time code and hands us the message to deliver; we just send the
 * SMS through {@see SmsService}.
 */
class PingramProvider implements VerificationProviderInterface
{
    public function __construct(private SmsService $sms) {}

    public function sendSms(string $phoneNumber, string $sms): bool
    {
        return $this->sms->send($phoneNumber, $sms);
    }

    public function getSlug(): string
    {
        return VerificationProviderConstants::PINGRAM_SLUG;
    }
}
