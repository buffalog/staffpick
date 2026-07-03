<?php

namespace Tests\Unit\StaffPick;

use App\Models\StaffPick\TenantConfig;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TenantConfigSecretTest extends TestCase
{
    public function test_it_returns_a_properly_encrypted_signing_secret(): void
    {
        $config = new TenantConfig;
        $config->setRawAttributes(['slack_signing_secret' => Crypt::encryptString('the-real-secret')]);

        $this->assertSame('the-real-secret', $config->slackSigningSecret());
    }

    public function test_an_undecryptable_secret_does_not_throw(): void
    {
        // Legacy plaintext under the `encrypted` cast (the bug that 500'd the inbound
        // Slack webhook). The accessor must degrade to the config fallback, not throw.
        $config = new TenantConfig;
        $config->setRawAttributes(['slack_signing_secret' => 'd8cf92135cf1cad946000000000000ff']);

        config(['services.slack.signing_secret' => null]);

        $this->assertNull($config->slackSigningSecret());
    }

    public function test_it_falls_back_to_the_config_secret_when_unset(): void
    {
        $config = new TenantConfig;
        $config->setRawAttributes(['slack_signing_secret' => null]);

        config(['services.slack.signing_secret' => 'env-level-secret']);

        $this->assertSame('env-level-secret', $config->slackSigningSecret());
    }
}
