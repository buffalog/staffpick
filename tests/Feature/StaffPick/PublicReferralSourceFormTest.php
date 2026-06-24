<?php

namespace Tests\Feature\StaffPick;

use App\Events\StaffPick\ReferralSourceRegistered;
use App\Livewire\StaffPick\PublicReferralSourceForm;
use App\Mail\StaffPick\ReferralSourceRegisteredApplicant;
use App\Mail\StaffPick\ReferralSourceRegisteredStaff;
use App\Models\StaffPick\ReferralSource;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class PublicReferralSourceFormTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->tenant = $this->createTenant();
    }

    public function test_the_registration_page_renders_for_a_valid_tenant(): void
    {
        $this->get(route('staffpick.referral-source.register', ['tenantSlug' => $this->tenant->uuid]))
            ->assertSuccessful()
            ->assertSee('Register as a referral source');
    }

    public function test_an_unknown_tenant_is_a_404(): void
    {
        $this->get(route('staffpick.referral-source.register', ['tenantSlug' => 'does-not-exist']))
            ->assertNotFound();
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(PublicReferralSourceForm::class, [
            'tenantId' => $this->tenant->id,
            'tenantName' => $this->tenant->name,
        ])
            ->set('data', ['name' => ''])
            ->call('submit')
            ->assertHasErrors([
                'data.name' => 'required',
                'data.contact_name' => 'required',
                'data.phone' => 'required',
                'data.email' => 'required',
            ]);
    }

    public function test_a_valid_submission_creates_a_pending_referral_source_and_sends_both_emails(): void
    {
        Livewire::test(PublicReferralSourceForm::class, [
            'tenantId' => $this->tenant->id,
            'tenantName' => $this->tenant->name,
        ])
            ->set('data', [
                'name' => 'Palm Beach Pediatrics',
                'contact_name' => 'Dr. Casey Nguyen',
                'phone' => '561-555-0100',
                'email' => 'intake@pbpeds.example.com',
                'city' => 'North Palm Beach',
                'state' => 'FL',
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $source = ReferralSource::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('name', 'Palm Beach Pediatrics')
            ->first();

        $this->assertNotNull($source);
        $this->assertSame(ReferralSource::STATUS_PENDING, $source->status);
        $this->assertSame('Dr. Casey Nguyen', $source->contact_name);
        $this->assertSame($this->tenant->id, $source->tenant_id);

        Mail::assertQueued(ReferralSourceRegisteredStaff::class);
        Mail::assertQueued(ReferralSourceRegisteredApplicant::class);
    }

    public function test_the_applicant_email_is_skipped_when_the_source_has_no_email(): void
    {
        // The public form requires an email, but the listener guards on filled()
        // for safety — exercise that path by dispatching the event directly.
        $source = ReferralSource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'status' => ReferralSource::STATUS_PENDING,
            'name' => 'No Email Agency',
            'contact_name' => 'Pat Doe',
            'phone' => '561-555-0199',
        ]);

        ReferralSourceRegistered::dispatch($source);

        Mail::assertQueued(ReferralSourceRegisteredStaff::class);
        Mail::assertNotQueued(ReferralSourceRegisteredApplicant::class);
    }
}
