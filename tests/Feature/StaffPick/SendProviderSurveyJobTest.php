<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\SendProviderSurvey;
use App\Mail\StaffPick\ProviderSurveyRequest;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\VerificationProviders\TwilioProvider;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Feature\FeatureTest;

class SendProviderSurveyJobTest extends FeatureTest
{
    private function assignment(Tenant $tenant, array $subjectAttributes = []): Assignment
    {
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $discipline->id]);
        $subject = Subject::factory()->create(array_merge(['tenant_id' => $tenant->id], $subjectAttributes));
        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
        ]);

        return Assignment::create([
            'tenant_id' => $tenant->id,
            'intake_request_id' => $intake->id,
            'provider_id' => $provider->id,
            'status' => Assignment::STATUS_OFFERED,
            'is_current' => true,
        ]);
    }

    private function twilioReturning(bool $result): TwilioProvider
    {
        $twilio = Mockery::mock(TwilioProvider::class);
        $twilio->shouldReceive('sendSms')->andReturn($result);

        return $twilio;
    }

    public function test_sends_via_sms_when_the_subject_has_a_phone(): void
    {
        $tenant = $this->createTenant();
        $assignment = $this->assignment($tenant, ['phone' => '5615551234', 'email' => 'a@example.com']);

        $twilio = Mockery::mock(TwilioProvider::class);
        $twilio->shouldReceive('sendSms')->once()->andReturn(true);

        (new SendProviderSurvey($assignment->id))->handle($twilio);

        $this->assertDatabaseHas('sp_provider_surveys', [
            'assignment_id' => $assignment->id,
            'delivery_channel' => ProviderSurvey::CHANNEL_SMS,
            'status' => ProviderSurvey::STATUS_SENT,
        ]);
    }

    public function test_falls_back_to_email_when_there_is_no_phone(): void
    {
        Mail::fake();
        $tenant = $this->createTenant();
        $assignment = $this->assignment($tenant, ['phone' => null, 'email' => 'patient@example.com']);

        (new SendProviderSurvey($assignment->id))->handle($this->twilioReturning(true));

        Mail::assertSent(ProviderSurveyRequest::class);
        $this->assertDatabaseHas('sp_provider_surveys', [
            'assignment_id' => $assignment->id,
            'delivery_channel' => ProviderSurvey::CHANNEL_EMAIL,
            'status' => ProviderSurvey::STATUS_SENT,
        ]);
    }

    public function test_bounces_when_the_subject_has_no_contact_method(): void
    {
        $tenant = $this->createTenant();
        $assignment = $this->assignment($tenant, ['phone' => null, 'email' => null]);

        (new SendProviderSurvey($assignment->id))->handle($this->twilioReturning(true));

        $this->assertDatabaseHas('sp_provider_surveys', [
            'assignment_id' => $assignment->id,
            'status' => ProviderSurvey::STATUS_BOUNCED,
        ]);
    }

    public function test_is_idempotent_and_does_not_create_a_second_survey(): void
    {
        $tenant = $this->createTenant();
        $assignment = $this->assignment($tenant, ['phone' => '5615551234']);

        ProviderSurvey::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'provider_id' => $assignment->provider_id,
            'subject_id' => $assignment->intakeRequest->subject_id,
            'status' => ProviderSurvey::STATUS_SENT,
        ]);

        $twilio = Mockery::mock(TwilioProvider::class);
        $twilio->shouldNotReceive('sendSms');

        (new SendProviderSurvey($assignment->id))->handle($twilio);

        $this->assertSame(1, ProviderSurvey::where('assignment_id', $assignment->id)->count());
    }
}
