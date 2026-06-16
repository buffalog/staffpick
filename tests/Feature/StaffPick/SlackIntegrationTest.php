<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\SendSlackNotification;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\StaffPick\SlackNotificationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FeatureTest;

class SlackIntegrationTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        // The inbound webhook tests assert real HTTP status codes (404/403/200);
        // FeatureTest disables exception handling, so re-enable it here.
        $this->withExceptionHandling();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Physical Therapy',
        ]);
    }

    /** Unique per tenant — FeatureTest shares the DB with no rollback, so a hardcoded
     * token would collide and the controller's first() would resolve another tenant. */
    private function inboundToken(): string
    {
        return 'inbound-token-'.$this->tenant->id;
    }

    private function configureSlack(array $overrides = []): TenantConfig
    {
        return TenantConfig::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            array_merge([
                'slack_webhook_url' => 'https://hooks.slack.test/webhook',
                'slack_signing_secret' => 'shhh-secret',
                'slack_inbound_token' => $this->inboundToken(),
                'slack_intake_keyword' => 'new referral',
            ], $overrides),
        );
    }

    private function service(): SlackNotificationService
    {
        return app(SlackNotificationService::class);
    }

    // ---- Outbound -----------------------------------------------------------

    public function test_intake_received_queues_a_slack_message_with_first_name_only(): void
    {
        Queue::fake();
        $this->configureSlack();

        $subject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Casey',
            'last_name' => 'Nguyen',
            'is_active' => true,
        ]);
        $source = ReferralSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Palm Beach Pediatrics',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);
        $intake = IntakeRequest::create([
            'tenant_id' => $this->tenant->id,
            'reference_number' => 'R-ABC234',
            'subject_id' => $subject->id,
            'referral_source_id' => $source->id,
            'discipline_id' => $this->discipline->id,
            'status' => 'pending',
        ]);

        $this->service()->notifyIntakeReceived($intake);

        Queue::assertPushed(SendSlackNotification::class, function (SendSlackNotification $job): bool {
            $json = json_encode($job->payload);

            return $job->webhookUrl === 'https://hooks.slack.test/webhook'
                && str_contains($json, 'R-ABC234')
                && str_contains($json, 'Casey')
                && ! str_contains($json, 'Nguyen') // HIPAA: last name not leaked
                && str_contains($json, 'Palm Beach Pediatrics');
        });
    }

    public function test_no_message_is_queued_when_no_webhook_is_configured(): void
    {
        Queue::fake();
        // No TenantConfig / no global webhook.

        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Casey', 'last_name' => 'N', 'is_active' => true]);
        $intake = IntakeRequest::create([
            'tenant_id' => $this->tenant->id,
            'reference_number' => 'R-NOPE12',
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
            'status' => 'pending',
        ]);

        $this->service()->notifyIntakeReceived($intake);

        Queue::assertNothingPushed();
    }

    public function test_provider_profile_submitted_queues_a_slack_message(): void
    {
        Queue::fake();
        $this->configureSlack();

        $provider = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
            'discipline_id' => $this->discipline->id,
        ]);

        $this->service()->notifyProviderProfileSubmitted($provider);

        Queue::assertPushed(SendSlackNotification::class, function (SendSlackNotification $job): bool {
            $json = json_encode($job->payload);

            return str_contains($json, 'Dana Rivera') && str_contains($json, 'Physical Therapy');
        });
    }

    public function test_credential_expiring_queues_a_slack_message(): void
    {
        Queue::fake();
        $this->configureSlack();

        $provider = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Dana',
            'last_name' => 'Rivera',
        ]);
        $type = CredentialDocumentType::create(['tenant_id' => $this->tenant->id, 'name' => 'State License']);

        // In-memory (not reloaded) so expires_at stays a Carbon — avoids the local
        // FreeTDS date-parse quirk on reading SQL Server `date` columns.
        $credential = new ProviderCredential([
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'expires_at' => now()->addDays(20),
            'status' => 'valid',
        ]);
        $credential->setRelation('provider', $provider);
        $credential->setRelation('documentType', $type);

        $this->service()->notifyCredentialExpiring($credential);

        Queue::assertPushed(SendSlackNotification::class, function (SendSlackNotification $job): bool {
            $json = json_encode($job->payload);

            return str_contains($json, 'State License') && str_contains($json, 'Dana Rivera');
        });
    }

    // ---- Inbound ------------------------------------------------------------

    private function postSigned(string $token, array $payload, string $secret, ?int $timestamp = null): TestResponse
    {
        $body = json_encode($payload);
        $timestamp ??= time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", $secret);

        return $this->call('POST', "/webhooks/slack/{$token}", [], [], [], [
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_an_unknown_inbound_token_is_a_404(): void
    {
        $this->postJson('/webhooks/slack/does-not-exist', ['type' => 'event_callback'])
            ->assertNotFound();
    }

    public function test_an_invalid_signature_is_rejected_and_logged(): void
    {
        $this->configureSlack();

        $this->postSigned($this->inboundToken(), ['type' => 'event_callback'], 'WRONG-secret')
            ->assertForbidden();

        $this->assertDatabaseHas('sp_slack_webhook_logs', [
            'tenant_id' => $this->tenant->id,
            'signature_valid' => false,
        ]);
        $this->assertSame(0, IntakeRequest::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_url_verification_challenge_is_echoed_back(): void
    {
        $this->configureSlack();

        $this->postSigned($this->inboundToken(), ['type' => 'url_verification', 'challenge' => 'abc123'], 'shhh-secret')
            ->assertOk()
            ->assertExactJson(['challenge' => 'abc123']);
    }

    public function test_a_keyword_message_creates_a_draft_intake_and_confirms(): void
    {
        Queue::fake();
        $this->configureSlack();

        $this->postSigned($this->inboundToken(), [
            'type' => 'event_callback',
            'event' => ['type' => 'message', 'text' => 'Please log a new referral for a patient', 'channel' => 'C12345'],
        ], 'shhh-secret')->assertOk();

        $intake = IntakeRequest::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('draft', $intake->status);
        $this->assertSame('C12345', $intake->slack_channel_id);
        $this->assertSame($this->tenant->id, $intake->tenant_id);

        $this->assertDatabaseHas('sp_slack_webhook_logs', [
            'tenant_id' => $this->tenant->id,
            'signature_valid' => true,
            'intake_request_id' => $intake->id,
        ]);

        Queue::assertPushed(SendSlackNotification::class, function (SendSlackNotification $job) use ($intake): bool {
            return str_contains(json_encode($job->payload), $intake->reference_number);
        });
    }

    public function test_a_message_without_the_keyword_creates_no_intake(): void
    {
        Queue::fake();
        $this->configureSlack();

        $this->postSigned($this->inboundToken(), [
            'type' => 'event_callback',
            'event' => ['type' => 'message', 'text' => 'just a normal message', 'channel' => 'C12345'],
        ], 'shhh-secret')->assertOk();

        $this->assertSame(0, IntakeRequest::where('tenant_id', $this->tenant->id)->count());
        $this->assertDatabaseHas('sp_slack_webhook_logs', [
            'tenant_id' => $this->tenant->id,
            'signature_valid' => true,
            'intake_request_id' => null,
        ]);
        Queue::assertNothingPushed();
    }
}
