<?php

namespace Tests\Feature\StaffPick;

use App\Livewire\StaffPick\PublicIntakeForm;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\ReferralSource;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\FeatureTest;

class PublicIntakeFormTest extends FeatureTest
{
    private Tenant $tenant;

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
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
        $this->source = ReferralSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Palm Beach Pediatrics',
            'email' => 'referrals@pbpeds.example.com',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);

        // Unique per test — FeatureTest shares the DB across tests with no
        // rollback, so a hardcoded token would collide on later tests.
        $this->source->ensureIntakeToken();
    }

    public function test_it_renders_for_a_valid_token(): void
    {
        Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->assertSuccessful()
            ->assertSee('New referral')
            ->assertSee('Palm Beach Pediatrics')
            ->assertSee('data-sp-leaflet="marker"', false);
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        Livewire::test(PublicIntakeForm::class, ['token' => 'does-not-exist']);
    }

    public function test_an_inactive_source_shows_the_inactive_notice_and_cannot_submit(): void
    {
        $this->source->update(['status' => 'inactive']);

        Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->assertSet('inactive', true)
            ->assertSee('no longer active');
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->set('data', ['first_name' => ''])
            ->call('submit')
            ->assertHasErrors([
                'data.first_name' => 'required',
                'data.last_name' => 'required',
                'data.address' => 'required',
                'data.city' => 'required',
                'data.state' => 'required',
                'data.discipline_id' => 'required',
            ]);
    }

    public function test_a_valid_submission_creates_a_pending_intake_and_shows_the_reference(): void
    {
        $component = Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->set('data', [
                'first_name' => 'Casey',
                'last_name' => 'Nguyen',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'zip' => '33408',
                'discipline_id' => $this->discipline->id,
                'frequency' => '2x/week',
                'notes' => 'Mornings preferred.',
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $intake = IntakeRequest::withoutGlobalScopes()
            ->where('referral_source_id', $this->source->id)
            ->first();

        $this->assertNotNull($intake);
        $this->assertSame('pending', $intake->status);
        $this->assertSame($this->tenant->id, $intake->tenant_id);
        $component->assertSet('referenceNumber', $intake->reference_number);
    }
}
