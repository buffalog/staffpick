<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Services\StaffPick\Credentialing\LicenseVerificationService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\FeatureTest;

class LicenseVerificationServiceTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        config()->set('services.rapidapi.key', 'test-rapidapi-key');
    }

    private function credential(array $typeAttrs, array $credentialAttrs = []): ProviderCredential
    {
        $type = CredentialDocumentType::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'State License',
            'verification_method' => 'manual',
        ], $typeAttrs));

        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'state' => 'FL']);

        // No expires_at — local FreeTDS can't read populated SQL Server date columns.
        return ProviderCredential::create(array_merge([
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
        ], $credentialAttrs));
    }

    public function test_api_verification_maps_clear_active_to_verified_and_stores_the_response(): void
    {
        Http::fake(['physical-therapy-license-verification.p.rapidapi.com/*' => Http::response([
            'status' => 'Clear/Active',
            'expiration_date' => '2027-01-31',
            'name' => 'Jordan Vega',
            'disciplinary_actions' => [],
            'source_url' => 'https://example.test/verify/PT12345',
        ], 200)]);

        $credential = $this->credential(
            ['name' => 'State License (PT)', 'verification_method' => 'api', 'api_discipline' => 'PT', 'rapidapi_host' => 'physical-therapy-license-verification.p.rapidapi.com'],
            ['license_number' => 'PT12345'],
        );

        $result = app(LicenseVerificationService::class)->verify($credential);

        $this->assertSame(ProviderCredential::VERIFICATION_VERIFIED, $result->status);
        $this->assertSame(ProviderCredential::SOURCE_API, $result->source);
        $this->assertSame('2027-01-31', $result->expirationDate);
        $this->assertSame('Jordan Vega', $result->nameOnLicense);
        $this->assertNotNull($result->verifiedAt);

        $fresh = $credential->fresh();
        $this->assertSame(ProviderCredential::VERIFICATION_VERIFIED, $fresh->verification_status);
        $this->assertSame(ProviderCredential::SOURCE_API, $fresh->verification_source);
        $this->assertSame('Clear/Active', $fresh->verification_response['status']);
        $this->assertNotNull($fresh->last_verified_at);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'physical-therapy-license-verification.p.rapidapi.com/verify')
                && $request->hasHeader('x-rapidapi-host', 'physical-therapy-license-verification.p.rapidapi.com')
                && $request->hasHeader('x-rapidapi-key', 'test-rapidapi-key')
                && $request['license_number'] === 'PT12345'
                && $request['state'] === 'FL';
        });
    }

    public function test_api_verification_sends_the_provider_state_normalized_to_uppercase(): void
    {
        Http::fake(['*' => Http::response(['status' => 'Clear/Active'], 200)]);

        // Provider model normalizes state on write — even a lowercase value reaches the
        // RapidAPI call as a clean 2-letter code.
        $type = CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'State License (PT)',
            'verification_method' => 'api',
            'rapidapi_host' => 'physical-therapy-license-verification.p.rapidapi.com',
        ]);
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'state' => 'fl']);
        $credential = ProviderCredential::create([
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
            'license_number' => 'PT54321',
        ]);

        app(LicenseVerificationService::class)->verify($credential);

        Http::assertSent(fn ($request): bool => $request['state'] === 'FL');
    }

    public function test_api_verification_maps_non_active_to_failed(): void
    {
        Http::fake(['*' => Http::response(['status' => 'Expired'], 200)]);

        $credential = $this->credential(
            ['name' => 'State License (PT)', 'verification_method' => 'api', 'rapidapi_host' => 'physical-therapy-license-verification.p.rapidapi.com'],
            ['license_number' => 'PT99999'],
        );

        $result = app(LicenseVerificationService::class)->verify($credential);

        $this->assertSame(ProviderCredential::VERIFICATION_FAILED, $result->status);
        $this->assertSame(ProviderCredential::VERIFICATION_FAILED, $credential->fresh()->verification_status);
    }

    public function test_deep_link_returns_the_interpolated_url_and_does_not_persist(): void
    {
        Http::fake(); // must not be hit

        $credential = $this->credential(
            ['name' => 'State License (OT)', 'verification_method' => 'deep_link', 'deep_link_url_template' => 'https://mqa.example/HealthCareProviders?LicenseNumber={license_number}&BoardCode=OT'],
            ['license_number' => 'OT-77', 'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED],
        );

        $result = app(LicenseVerificationService::class)->verify($credential);

        $this->assertSame(ProviderCredential::SOURCE_DEEP_LINK, $result->source);
        $this->assertSame('https://mqa.example/HealthCareProviders?LicenseNumber=OT-77&BoardCode=OT', $result->deepLinkUrl);
        // verify() itself does not change the credential for deep_link.
        $this->assertSame(ProviderCredential::VERIFICATION_UNVERIFIED, $credential->fresh()->verification_status);
        Http::assertNothingSent();
    }

    public function test_manual_is_a_no_op(): void
    {
        Http::fake();

        $credential = $this->credential(['name' => 'CPR Certification', 'verification_method' => 'manual']);

        $result = app(LicenseVerificationService::class)->verify($credential);

        $this->assertSame(ProviderCredential::SOURCE_MANUAL, $result->source);
        $this->assertSame(ProviderCredential::VERIFICATION_UNVERIFIED, $credential->fresh()->verification_status);
        Http::assertNothingSent();
    }
}
