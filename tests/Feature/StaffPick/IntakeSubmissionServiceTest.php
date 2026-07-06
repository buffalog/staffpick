<?php

namespace Tests\Feature\StaffPick;

use App\Mail\StaffPick\IntakeReceivedReferrer;
use App\Mail\StaffPick\IntakeSubmittedStaff;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Specialty;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffPick\IntakeSubmissionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\FeatureTest;

class IntakeSubmissionServiceTest extends FeatureTest
{
    private Tenant $tenant;

    private User $staff;

    private ReferralSource $source;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '26.8205600', 'lon' => '-80.0533670'],
        ])]);

        $this->tenant = $this->createTenant();
        $this->staff = $this->createUser($this->tenant);
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
        $this->source = ReferralSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Palm Beach Pediatrics',
            'email' => 'referrals@pbpeds.example.com',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);
    }

    private function service(): IntakeSubmissionService
    {
        return app(IntakeSubmissionService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function intakeData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Casey',
            'last_name' => 'Nguyen',
            'email' => 'casey@example.com',
            'phone' => '5615551234',
            'address' => '340 US-1',
            'city' => 'North Palm Beach',
            'state' => 'FL',
            'zip' => '33408',
            'gender' => 'female',
            'preferred_language' => 'English',
            'diagnosis' => 'Post-surgical rehab',
            'discipline_id' => $this->discipline->id,
            'visit_type' => 'Home health',
            'frequency' => '2x/week',
            'visits_authorized' => 12,
            'authorization_number' => 'AUTH-77',
            'radius_miles' => 20,
            'notes' => 'Prefers morning visits.',
        ], $overrides);
    }

    public function test_submit_creates_a_pending_intake_attributed_to_the_source(): void
    {
        $intake = $this->service()->submit($this->source, $this->intakeData());

        $this->assertSame('pending', $intake->status);
        $this->assertSame($this->tenant->id, $intake->tenant_id);
        $this->assertSame($this->source->id, $intake->referral_source_id);
        $this->assertSame($this->discipline->id, $intake->discipline_id);
        $this->assertSame('2x/week', $intake->frequency);
        $this->assertSame(12, $intake->visits_authorized);
        $this->assertNull($intake->acknowledged_at);
    }

    public function test_submit_stores_referring_clinician_fields_on_the_intake(): void
    {
        $intake = $this->service()->submit($this->source, $this->intakeData([
            'referring_clinician_name' => 'Dana Okafor, RN',
            'referring_clinician_phone' => '5615550199',
        ]));

        $this->assertSame('Dana Okafor, RN', $intake->referring_clinician_name);
        $this->assertSame('5615550199', $intake->referring_clinician_phone);
    }

    public function test_submit_generates_a_reference_number_in_the_r_format(): void
    {
        $intake = $this->service()->submit($this->source, $this->intakeData());

        $this->assertMatchesRegularExpression('/^R-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $intake->reference_number);
    }

    public function test_submit_creates_a_geocoded_subject_with_the_demographics(): void
    {
        $intake = $this->service()->submit($this->source, $this->intakeData());

        $subject = Subject::withoutGlobalScopes()->find($intake->subject_id);
        $this->assertNotNull($subject);
        $this->assertSame('Casey', $subject->first_name);
        $this->assertSame($this->tenant->id, (int) $subject->tenant_id);
        $this->assertSame('Post-surgical rehab', $subject->diagnosis);
        // Geocoded from the faked Nominatim response.
        $this->assertSame(26.82056, (float) $subject->latitude);
        $this->assertSame(-80.053367, (float) $subject->longitude);
    }

    public function test_submit_uses_supplied_coordinates_without_geocoding(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 500)]);

        $intake = $this->service()->submit($this->source, $this->intakeData([
            'latitude' => 26.7153,
            'longitude' => -80.0534,
        ]));

        $subject = Subject::withoutGlobalScopes()->find($intake->subject_id);
        $this->assertSame(26.7153, (float) $subject->latitude);
        $this->assertSame(-80.0534, (float) $subject->longitude);
    }

    public function test_submit_notifies_staff_and_queues_both_confirmation_emails(): void
    {
        $this->service()->submit($this->source, $this->intakeData());

        // Staff in-app (database) notification.
        $this->assertGreaterThan(0, $this->staff->fresh()->notifications()->count());

        Mail::assertQueued(IntakeSubmittedStaff::class);
        Mail::assertQueued(IntakeReceivedReferrer::class, fn (IntakeReceivedReferrer $mail): bool => $mail->hasTo('referrals@pbpeds.example.com'));
    }

    public function test_submit_only_syncs_specialties_belonging_to_the_source_tenant(): void
    {
        $ownSpecialty = Specialty::create(['tenant_id' => $this->tenant->id, 'name' => 'Pediatrics', 'is_active' => true]);

        // A specialty owned by a DIFFERENT tenant — a forged Livewire payload could
        // submit its id, and it must not be attached to this tenant's intake.
        $otherTenant = $this->createTenant();
        $foreignSpecialty = Specialty::create(['tenant_id' => $otherTenant->id, 'name' => 'Cardiology', 'is_active' => true]);

        $intake = $this->service()->submit($this->source, $this->intakeData([
            'specialty_ids' => [$ownSpecialty->id, $foreignSpecialty->id, 999999],
        ]));

        $syncedIds = $intake->specialties()->pluck('sp_specialties.id')->all();

        $this->assertSame([$ownSpecialty->id], $syncedIds);
        $this->assertNotContains($foreignSpecialty->id, $syncedIds);
    }

    public function test_reference_numbers_are_unique_within_a_tenant(): void
    {
        $service = $this->service();

        $subject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Ref',
            'last_name' => 'Holder',
            'is_active' => true,
        ]);

        $a = $service->generateReferenceNumber((int) $this->tenant->id);
        IntakeRequest::create([
            'tenant_id' => $this->tenant->id,
            'reference_number' => $a,
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);

        $b = $service->generateReferenceNumber((int) $this->tenant->id);

        $this->assertNotSame($a, $b);
    }
}
