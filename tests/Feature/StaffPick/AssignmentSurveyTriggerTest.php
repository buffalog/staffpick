<?php

namespace Tests\Feature\StaffPick;

use App\Jobs\StaffPick\SendProviderSurvey;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\FeatureTest;

class AssignmentSurveyTriggerTest extends FeatureTest
{
    private function assignment(Tenant $tenant, string $status = Assignment::STATUS_OFFERED): Assignment
    {
        $discipline = Discipline::create(['tenant_id' => $tenant->id, 'name' => 'Physical Therapy']);
        $provider = Provider::factory()->create(['tenant_id' => $tenant->id, 'discipline_id' => $discipline->id]);
        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);
        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $discipline->id,
        ]);

        return Assignment::create([
            'tenant_id' => $tenant->id,
            'intake_request_id' => $intake->id,
            'provider_id' => $provider->id,
            'status' => $status,
            'is_current' => true,
        ]);
    }

    public function test_completing_an_assignment_dispatches_the_survey_job(): void
    {
        Bus::fake();
        $tenant = $this->createTenant();
        $assignment = $this->assignment($tenant);

        $assignment->update(['status' => Assignment::STATUS_COMPLETED, 'completed_at' => now()]);

        Bus::assertDispatched(
            SendProviderSurvey::class,
            fn (SendProviderSurvey $job): bool => $job->assignmentId === $assignment->id,
        );
    }

    public function test_a_non_completion_status_change_does_not_dispatch(): void
    {
        Bus::fake();
        $tenant = $this->createTenant();
        $assignment = $this->assignment($tenant);

        $assignment->update(['status' => Assignment::STATUS_ACTIVE]);

        Bus::assertNotDispatched(SendProviderSurvey::class);
    }

    public function test_an_assignment_created_already_completed_dispatches(): void
    {
        Bus::fake();
        $tenant = $this->createTenant();

        $this->assignment($tenant, Assignment::STATUS_COMPLETED);

        Bus::assertDispatched(SendProviderSurvey::class);
    }
}
