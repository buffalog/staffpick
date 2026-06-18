<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\TenantConfig;
use App\Models\Tenant;
use App\Services\StaffPick\SchedulerNotificationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Feature\FeatureTest;

class SchedulerNoClinicianAlertTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Deliberately do NOT set a current Filament panel — the real exhaustion path
        // runs from the CheckOfferExpiry cron / queued jobs with no panel context, so
        // the case link must resolve via the explicit panel: 'dashboard' argument.
        $this->tenant = $this->createTenant();
    }

    public function test_slack_alert_fires_even_when_email_delivery_throws_and_carries_the_required_fields(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        $admin = $this->createTenantAdmin($this->tenant, ['email' => 'admin@fcts.example']);
        TenantConfig::create([
            'tenant_id' => $this->tenant->id,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/secret',
        ]);

        $discipline = Discipline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Physical Therapy',
            'abbreviation' => 'PT',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $source = ReferralSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Palm Beach Pediatrics',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);
        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'no_clinicians_available',
            'reference_number' => 'R-NOCLIN1',
            'discipline_id' => $discipline->id,
            'referral_source_id' => $source->id,
        ]);

        // Simulate a down mailer. The sync queue sends inline, so this throws right
        // where the real bug surfaced — it must NOT abort the Slack alert that follows.
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP 530 authentication required'));

        app(SchedulerNotificationService::class)->notifyNoClinicians($intake->fresh());

        // Slack posted despite the email failure, with reference, discipline, referral
        // source, and a direct /intake-requests/{id} link. (str_replace undoes JSON's
        // escaped slashes so the link substring matches.)
        Http::assertSent(function ($request) use ($source, $intake): bool {
            $body = str_replace('\/', '/', $request->body());

            return str_contains($request->url(), 'hooks.slack.com')
                && str_contains($body, 'R-NOCLIN1')
                && str_contains($body, 'Physical Therapy')
                && str_contains($body, $source->name)
                && str_contains($body, 'intake-requests/'.$intake->id);
        });

        // The bell still persisted for the admin.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
    }
}
