<?php

namespace Tests\Feature\StaffPick;

use App\Livewire\StaffPick\PublicIntakeForm;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\SlackNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
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
        $this->assertSame($this->tenant->id, (int) $intake->tenant_id);
        $component->assertSet('referenceNumber', $intake->reference_number);
    }

    public function test_the_provider_language_options_come_from_sp_languages_ordered_by_name(): void
    {
        // Idempotent across the shared-DB FeatureTest run; distinctive names/codes.
        Language::firstOrCreate(['code' => 'zzt'], ['name' => 'Zzz Test Language']);
        Language::firstOrCreate(['code' => 'aat'], ['name' => 'Aaa Test Language']);

        $options = Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->instance()->languageOptions();

        // A plain list of names (not id-keyed), ordered alphabetically.
        $this->assertContains('Aaa Test Language', $options);
        $this->assertContains('Zzz Test Language', $options);
        $this->assertLessThan(
            array_search('Zzz Test Language', $options, true),
            array_search('Aaa Test Language', $options, true),
        );
    }

    public function test_a_submission_stores_the_selected_provider_language_by_name(): void
    {
        Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->set('data', [
                'first_name' => 'Casey',
                'last_name' => 'Nguyen',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'discipline_id' => $this->discipline->id,
                // The combobox writes the language NAME, which is what the matching
                // engine compares against provider language names/codes.
                'language_preference' => 'Spanish',
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $intake = IntakeRequest::withoutGlobalScopes()
            ->where('referral_source_id', $this->source->id)
            ->first();
        $subject = Subject::withoutGlobalScopes()->find($intake->subject_id);

        $this->assertSame('Spanish', $subject->language_preference);
    }

    public function test_a_submission_stores_the_patient_preferred_language_by_name(): void
    {
        Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->set('data', [
                'first_name' => 'Casey',
                'last_name' => 'Nguyen',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'discipline_id' => $this->discipline->id,
                'preferred_language' => 'Haitian Creole',
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $intake = IntakeRequest::withoutGlobalScopes()
            ->where('referral_source_id', $this->source->id)
            ->first();
        $subject = Subject::withoutGlobalScopes()->find($intake->subject_id);

        $this->assertSame('Haitian Creole', $subject->preferred_language);
    }

    public function test_a_failing_notification_channel_does_not_break_the_confirmation(): void
    {
        // A post-commit side effect (Slack) blows up — the intake is already saved,
        // so the submitter must still get a clean confirmation, not a 500.
        $slack = Mockery::mock(SlackNotificationService::class);
        $slack->shouldReceive('notifyIntakeReceived')->andThrow(new RuntimeException('slack down'));
        $this->app->instance(SlackNotificationService::class, $slack);

        $component = Livewire::test(PublicIntakeForm::class, ['token' => $this->source->intake_token])
            ->set('data', [
                'first_name' => 'Casey',
                'last_name' => 'Nguyen',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'discipline_id' => $this->discipline->id,
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $intake = IntakeRequest::withoutGlobalScopes()
            ->where('referral_source_id', $this->source->id)
            ->first();

        $this->assertNotNull($intake);
        $component->assertSet('referenceNumber', $intake->reference_number);
    }
}
