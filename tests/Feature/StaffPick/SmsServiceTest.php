<?php

namespace Tests\Feature\StaffPick;

use App\Services\StaffPick\SmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pingram.base_url', 'https://api.pingram.io');
        config()->set('services.pingram.api_key', 'pingram_sk_test123');
        config()->set('services.pingram.sms_type', 'staffpick_sms');
    }

    public function test_it_posts_the_expected_payload_to_pingram(): void
    {
        Http::fake(['api.pingram.io/*' => Http::response(['id' => 'msg_1'], 200)]);

        $result = app(SmsService::class)->send('+15615551234', 'Your visit is confirmed.');

        $this->assertTrue($result);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.pingram.io/sms'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer pingram_sk_test123')
                && $request['type'] === 'staffpick_sms'
                && $request['to'] === '+15615551234'
                && $request['message'] === 'Your visit is confirmed.';
        });
    }

    public function test_it_returns_false_without_throwing_on_api_error(): void
    {
        Http::fake(['api.pingram.io/*' => Http::response(['error' => 'bad'], 500)]);

        $this->assertFalse(app(SmsService::class)->send('+15615551234', 'Hi'));

        Http::assertSentCount(1);
    }

    public function test_it_is_a_no_op_when_no_api_key_is_configured(): void
    {
        config()->set('services.pingram.api_key', null);
        Http::fake();

        $this->assertFalse(app(SmsService::class)->send('+15615551234', 'Hi'));

        Http::assertNothingSent();
    }
}
