<?php

namespace App\Services\StaffPick\Credentialing;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\ProviderCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Three-tier license verification. Dispatches on the credential type's
 * verification_method:
 *  - 'api'       — RapidAPI license lookup (PT/PTA, 43 states); persists the mapped
 *                  status + raw response on the credential.
 *  - 'deep_link' — returns a pre-filled state-board URL for a staffer to open; does
 *                  not change the credential (the caller marks it pending).
 *  - 'manual'    — no-op; a staffer marks it verified/failed by hand.
 */
class LicenseVerificationService
{
    public function verify(ProviderCredential $credential): VerificationResult
    {
        $type = $credential->documentType;

        return match ($type?->verification_method) {
            CredentialDocumentType::METHOD_API => $this->verifyViaApi($credential, $type),
            CredentialDocumentType::METHOD_DEEP_LINK => $this->buildDeepLink($credential, $type),
            default => $this->manual($credential),
        };
    }

    private function verifyViaApi(ProviderCredential $credential, CredentialDocumentType $type): VerificationResult
    {
        $host = (string) $type->rapidapi_host;
        $now = now();

        try {
            $response = Http::withHeaders([
                'x-rapidapi-host' => $host,
                'x-rapidapi-key' => (string) config('services.rapidapi.key'),
            ])->timeout(15)->get("https://{$host}/verify", [
                'license_number' => $credential->license_number,
                'state' => $credential->provider?->state,
            ]);

            $data = is_array($response->json()) ? $response->json() : ['body' => $response->body()];
        } catch (Throwable $e) {
            Log::warning('License verification API call failed.', ['credential' => $credential->id, 'error' => $e->getMessage()]);
            $data = ['error' => $e->getMessage()];
        }

        // Per spec: licence "Clear/Active" verifies; anything else fails.
        $status = (data_get($data, 'status') === 'Clear/Active')
            ? ProviderCredential::VERIFICATION_VERIFIED
            : ProviderCredential::VERIFICATION_FAILED;

        $credential->update([
            'verification_status' => $status,
            'verification_source' => ProviderCredential::SOURCE_API,
            'last_verified_at' => $now,
            'verification_response' => $data,
        ]);

        return new VerificationResult(
            status: $status,
            source: ProviderCredential::SOURCE_API,
            verifiedAt: $now,
            expirationDate: data_get($data, 'expiration_date'),
            nameOnLicense: data_get($data, 'name'),
            disciplinaryActions: data_get($data, 'disciplinary_actions'),
            sourceUrl: data_get($data, 'source_url'),
            rawResponse: $data,
        );
    }

    private function buildDeepLink(ProviderCredential $credential, CredentialDocumentType $type): VerificationResult
    {
        $url = str_replace(
            '{license_number}',
            rawurlencode((string) $credential->license_number),
            (string) $type->deep_link_url_template,
        );

        return new VerificationResult(
            status: $credential->verification_status ?? ProviderCredential::VERIFICATION_UNVERIFIED,
            source: ProviderCredential::SOURCE_DEEP_LINK,
            deepLinkUrl: $url,
        );
    }

    private function manual(ProviderCredential $credential): VerificationResult
    {
        return new VerificationResult(
            status: $credential->verification_status ?? ProviderCredential::VERIFICATION_UNVERIFIED,
            source: ProviderCredential::SOURCE_MANUAL,
        );
    }
}
