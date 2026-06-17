<?php

namespace Tests\Feature\StaffPick;

use App\Livewire\StaffPick\PublicIntakeForm;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
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

        $this->assertCount(14, $ptNames);
        $this->assertContains('Orthopaedics', $ptNames);
        $this->assertContains("Women's/Pelvic Health", $ptNames);
        $this->assertNotContains('Gerontology (BCG)', $ptNames);              // OT-only
        $this->assertNotContains('Swallowing Disorders (Dysphagia)', $ptNames); // SLP-only

        $this->assertContains('Swallowing Disorders (Dysphagia)', $slp->specialties()->pluck('name')->all());
    }

    public function test_other_write_in_is_a_single_row_mapped_to_every_discipline(): void
    {
        $other = Specialty::where('tenant_id', $this->tenant->id)->where('name', Specialty::OTHER_NAME)->get();

        // "Other (write in)" appears in all three lists but is created once.
        $this->assertCount(1, $other);

        $disciplineAbbreviations = $other->first()->disciplines()->pluck('abbreviation')->all();
        $this->assertContains('PT', $disciplineAbbreviations);
        $this->assertContains('OT', $disciplineAbbreviations);
        $this->assertContains('SLP', $disciplineAbbreviations);
    }

    public function test_the_seeder_splits_state_license_into_three_with_verification_methods(): void
    {
        $byName = fn (string $name): ?CredentialDocumentType => CredentialDocumentType::where('tenant_id', $this->tenant->id)->where('name', $name)->first();

        $pt = $byName('State License (PT)');
        $this->assertNotNull($pt);
        $this->assertSame('api', $pt->verification_method);
        $this->assertSame('physical-therapy-license-verification.p.rapidapi.com', $pt->rapidapi_host);

        $ot = $byName('State License (OT)');
        $this->assertSame('deep_link', $ot->verification_method);
        $this->assertStringContainsString('BoardCode=OT', $ot->deep_link_url_template);
        $this->assertStringContainsString('{license_number}', $ot->deep_link_url_template);

        $this->assertSame('deep_link', $byName('State License (SLP)')->verification_method);
        $this->assertSame('manual', $byName('CPR Certification')->verification_method);

        // The pre-split single type is retired.
        $legacy = $byName('State License');
        $this->assertTrue($legacy === null || $legacy->is_active === false);
    }

    public function test_legacy_state_license_credentials_are_repointed_by_discipline(): void
    {
        // Shared DB, no rollback — isolate the credentials this test inspects.
        ProviderCredential::query()->delete();

        $legacy = CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'State License',
            'verification_method' => 'manual',
        ]);

        $unknownDiscipline = Discipline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Audiology',
            'abbreviation' => 'AUD',
            'sort_order' => 9,
            'is_active' => true,
        ]);

        $ptCred = $this->legacyCredential($this->providerWith($this->discipline('PT')->id), $legacy);
        $otCred = $this->legacyCredential($this->providerWith($this->discipline('OT')->id), $legacy);
        $noDisciplineCred = $this->legacyCredential($this->providerWith(null), $legacy);
        $unknownCred = $this->legacyCredential($this->providerWith($unknownDiscipline->id), $legacy);

        (new TenantTaxonomySeeder)->seedForTenant($this->tenant);

        $byName = fn (string $name): int => CredentialDocumentType::where('tenant_id', $this->tenant->id)->where('name', $name)->value('id');

        // PT/OT credentials moved to their per-discipline type.
        $this->assertSame($byName('State License (PT)'), (int) $ptCred->fresh()->document_type_id);
        $this->assertSame($byName('State License (OT)'), (int) $otCred->fresh()->document_type_id);

        // No discipline / unknown discipline: left on the legacy type, not guessed.
        $this->assertSame($legacy->id, (int) $noDisciplineCred->fresh()->document_type_id);
        $this->assertSame($legacy->id, (int) $unknownCred->fresh()->document_type_id);

        // Idempotent: re-running moves nothing further and doesn't error.
        (new TenantTaxonomySeeder)->seedForTenant($this->tenant);
        $this->assertSame($byName('State License (PT)'), (int) $ptCred->fresh()->document_type_id);
        $this->assertSame($legacy->id, (int) $noDisciplineCred->fresh()->document_type_id);
    }

    private function providerWith(?int $disciplineId): Provider
    {
        return Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $disciplineId,
        ]);
    }

    private function legacyCredential(Provider $provider, CredentialDocumentType $legacy): ProviderCredential
    {
        // Null expires_at — local FreeTDS can't read populated SQL Server date columns.
        return ProviderCredential::create([
            'provider_id' => $provider->id,
            'document_type_id' => $legacy->id,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
        ]);
    }

    public function test_seeding_is_idempotent(): void
    {
        (new TenantTaxonomySeeder)->seedForTenant($this->tenant);

        $this->assertSame(14, $this->discipline('PT')->specialties()->count());
        $this->assertSame(1, Specialty::where('tenant_id', $this->tenant->id)->where('name', Specialty::OTHER_NAME)->count());
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
        $this->assertContains('Orthopaedics', $ptOptions);
        $this->assertNotContains('Swallowing Disorders (Dysphagia)', $ptOptions);

        // Switching discipline clears the prior selection and re-filters.
        $component
            ->set('data.specialty_ids', [array_key_first($component->instance()->specialtyOptions())])
            ->set('data.discipline_id', $slp->id)
            ->assertSet('data.specialty_ids', []);

        $slpOptions = array_values($component->instance()->specialtyOptions());
        $this->assertContains('Swallowing Disorders (Dysphagia)', $slpOptions);
        $this->assertNotContains('Orthopaedics', $slpOptions);
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
        $ortho = Specialty::where('tenant_id', $this->tenant->id)->where('name', 'Orthopaedics')->firstOrFail();

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
