<?php

namespace Tests\Feature\StaffPick;

use App\Services\StaffPick\SmsService;
use App\Services\VerificationProviders\PingramProvider;
use Mockery;
use Tests\TestCase;

class PingramProviderTest extends TestCase
{
    public function test_it_delegates_sending_to_the_sms_service(): void
    {
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->with('+15615551234', 'code 123')->andReturn(true);

        $provider = new PingramProvider($sms);

        $this->assertTrue($provider->sendSms('+15615551234', 'code 123'));
    }

    public function test_its_slug_is_pingram(): void
    {
        $provider = new PingramProvider(Mockery::mock(SmsService::class));

        $this->assertSame('pingram', $provider->getSlug());
    }
}
