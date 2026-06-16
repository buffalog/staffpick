<?php

namespace Tests\Feature\StaffPick;

use App\Livewire\StaffPick\PublicIntakeForm;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Specialty;
use App\Models\Tenant;
use Database\Seeders\TenantTaxonomySeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class DisciplineSpecialtyTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        (new TenantTaxonomySeeder)->seedForTenant($this->tenant);
    }

    private function discipline(string $abbreviation): Discipline
    {
        return Discipline::where('tenant_id', $this->tenant->id)
            ->where('abbreviation', $abbreviation)
            ->firstOrFail();
    }

    public function test_the_seeder_maps_specialties_to_their_disciplines(): void
    {
        $pt = $this->discipline('PT');
        $slp = $this->discipline('SLP');

        $ptNames = $pt->specialties()->pluck('name')->all();

        $this->assertCount(7, $ptNames);
        $this->assertContains('Orthopedics', $ptNames);
        $this->assertContains("Women's Health", $ptNames);
        $this->assertNotContains('Hand Therapy', $ptNames); // OT-only
        $this->assertNotContains('Dysphagia', $ptNames);     // SLP-only

        $this->assertContains('Dysphagia', $slp->specialties()->pluck('name')->all());
    }

    public function test_a_shared_specialty_is_a_single_row_mapped_to_multiple_disciplines(): void
    {
        $geriatrics = Specialty::where('tenant_id', $this->tenant->id)->where('name', 'Geriatrics')->get();

        // Geriatrics appears under both PT and OT, but is created once.
        $this->assertCount(1, $geriatrics);

        $disciplineAbbreviations = $geriatrics->first()->disciplines()->pluck('abbreviation')->all();
        $this->assertContains('PT', $disciplineAbbreviations);
        $this->assertContains('OT', $disciplineAbbreviations);
    }

    public function test_seeding_is_idempotent(): void
    {
        (new TenantTaxonomySeeder)->seedForTenant($this->tenant);

        $this->assertSame(7, $this->discipline('PT')->specialties()->count());
        $this->assertSame(1, Specialty::where('tenant_id', $this->tenant->id)->where('name', 'Geriatrics')->count());
    }

    public function test_public_intake_filters_specialties_by_discipline_and_clears_on_change(): void
    {
        Mail::fake();
        $source = $this->activeSource();
        $pt = $this->discipline('PT');
        $slp = $this->discipline('SLP');

        $component = Livewire::test(PublicIntakeForm::class, ['token' => $source->intake_token]);

        // No discipline selected → no specialties offered.
        $this->assertSame([], $component->instance()->specialtyOptions());

        $component->set('data.discipline_id', $pt->id);
        $ptOptions = array_values($component->instance()->specialtyOptions());
        $this->assertContains('Orthopedics', $ptOptions);
        $this->assertNotContains('Dysphagia', $ptOptions);

        // Switching discipline clears the prior selection and re-filters.
        $component
            ->set('data.specialty_ids', [array_key_first($component->instance()->specialtyOptions())])
            ->set('data.discipline_id', $slp->id)
            ->assertSet('data.specialty_ids', []);

        $slpOptions = array_values($component->instance()->specialtyOptions());
        $this->assertContains('Dysphagia', $slpOptions);
        $this->assertNotContains('Orthopedics', $slpOptions);
    }

    public function test_a_submission_persists_requested_specialties(): void
    {
        Mail::fake();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '26.8205600', 'lon' => '-80.0533670'],
        ])]);
        RateLimiter::clear('intake-submit:127.0.0.1');

        $source = $this->activeSource();
        $pt = $this->discipline('PT');
        $ortho = Specialty::where('tenant_id', $this->tenant->id)->where('name', 'Orthopedics')->firstOrFail();

        Livewire::test(PublicIntakeForm::class, ['token' => $source->intake_token])
            ->set('data', [
                'first_name' => 'Casey',
                'last_name' => 'Nguyen',
                'address' => '340 US-1',
                'city' => 'North Palm Beach',
                'state' => 'FL',
                'discipline_id' => $pt->id,
                'specialty_ids' => [$ortho->id],
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $intake = IntakeRequest::withoutGlobalScopes()->where('referral_source_id', $source->id)->firstOrFail();
        $this->assertTrue($intake->specialties()->where('sp_specialties.id', $ortho->id)->exists());
    }

    private function activeSource(): ReferralSource
    {
        $source = ReferralSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Palm Beach Pediatrics',
            'email' => 'referrals@pbpeds.example.com',
            'status' => ReferralSource::STATUS_ACTIVE,
        ]);

        $source->ensureIntakeToken();

        return $source;
    }
}
