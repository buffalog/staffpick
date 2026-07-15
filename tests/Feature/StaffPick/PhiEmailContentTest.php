<?php

namespace Tests\Feature\StaffPick;

use App\Mail\StaffPick\IntakeReceivedReferrer;
use App\Mail\StaffPick\IntakeSubmittedStaff;
use App\Mail\StaffPick\ProviderSurveyRequest;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

/**
 * These bodies transit the mail/SMS vendor (no BAA). They must carry NO patient name and NO
 * treatment fact ("therapy"). Sentinel patient names would only appear if a name leaked.
 */
class PhiEmailContentTest extends FeatureTest
{
    private const SENTINEL_FIRST = 'Zzyxpatient';

    private const SENTINEL_LAST = 'Qqwlastname';

    private function intakeWithSentinelPatient(Tenant $tenant): IntakeRequest
    {
        $subject = Subject::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => self::SENTINEL_FIRST,
            'last_name' => self::SENTINEL_LAST,
        ]);
        $source = ReferralSource::create([
            'tenant_id' => $tenant->id,
            'name' => 'Palm Beach Pediatrics',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);

        return IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'reference_number' => 'R-EMAIL01',
            'subject_id' => $subject->id,
            'referral_source_id' => $source->id,
            'discipline_id' => $discipline->id,
        ]);
    }

    public function test_intake_submitted_staff_email_has_no_patient_name(): void
    {
        $intake = $this->intakeWithSentinelPatient($this->createTenant());

        $html = (new IntakeSubmittedStaff($intake))->render();

        $this->assertStringContainsString('R-EMAIL01', $html); // reference intact
        $this->assertStringNotContainsString(self::SENTINEL_FIRST, $html);
        $this->assertStringNotContainsString(self::SENTINEL_LAST, $html);
    }

    public function test_intake_received_referrer_email_has_no_patient_name(): void
    {
        $intake = $this->intakeWithSentinelPatient($this->createTenant());

        $html = (new IntakeReceivedReferrer($intake))->render();

        $this->assertStringContainsString('R-EMAIL01', $html);
        $this->assertStringNotContainsString(self::SENTINEL_FIRST, $html);
        $this->assertStringNotContainsString(self::SENTINEL_LAST, $html);
    }

    public function test_provider_survey_email_reveals_no_treatment_fact(): void
    {
        $tenant = $this->createTenant();
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id]);
        $survey = ProviderSurvey::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => 1,
            'provider_id' => $provider->id,
            'subject_id' => 1,
            'status' => ProviderSurvey::STATUS_SENT,
            'token' => Str::random(48),
        ]);

        $mailable = new ProviderSurveyRequest($survey);
        $html = $mailable->render();
        $subject = $mailable->envelope()->subject;

        // No treatment fact in the subject line or the body copy.
        $this->assertStringNotContainsStringIgnoringCase('therapy', (string) $subject);
        $this->assertStringNotContainsStringIgnoringCase('therapy', $html);
        $this->assertStringNotContainsStringIgnoringCase('provider', $html);
        // Feature intact: the survey link is present.
        $this->assertStringContainsString($survey->responseUrl(), $html);
    }
}
