<?php

namespace App\Services\StaffPick;

use App\Constants\InvitationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Mail\StaffPick\ProviderApplicationRejected;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderApplication;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Staff review of public provider applications: approve (map into a real provider,
 * move credential files, create pending credentials, invite the provider) or reject
 * (flag + notify the applicant).
 */
class ProviderApplicationReviewService
{
    public function __construct(
        private TenantService $tenants,
    ) {}

    public function approve(ProviderApplication $application, User $reviewer): Provider
    {
        $provider = DB::transaction(function () use ($application, $reviewer): Provider {
            $provider = Provider::create([
                'tenant_id' => $application->tenant_id,
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->street_address,
                'city' => $application->city,
                'state' => $application->state,
                'zip' => $application->zip,
                'latitude' => $application->latitude,
                'longitude' => $application->longitude,
                'discipline_id' => $this->resolveDisciplineId($application),
                'gender' => $application->gender,
                'is_contractor' => (bool) $application->is_contractor,
                'radius_preferred_miles' => $application->preferred_radius ?? 15,
                'radius_max_miles' => $application->maximum_radius ?? 25,
                // Pending until staff verify credentials and activate — not yet matchable.
                'status' => Provider::STATUS_PENDING,
                'is_active' => false,
                'submitted_at' => now(),
            ]);

            $provider->specialties()->sync(array_values($application->specialties ?? []));

            $this->createServiceZone($provider, $application);
            $this->importCredentials($provider, $application);

            $application->update([
                'status' => ProviderApplication::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $provider;
        });

        $this->inviteProvider($application);

        return $provider;
    }

    public function reject(ProviderApplication $application, User $reviewer, string $reason): ProviderApplication
    {
        $application->update([
            'status' => ProviderApplication::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        if (filled($application->email)) {
            Mail::to($application->email)->queue(new ProviderApplicationRejected($application));
        }

        return $application;
    }

    private function resolveDisciplineId(ProviderApplication $application): ?int
    {
        if (blank($application->discipline)) {
            return null;
        }

        return Discipline::withoutGlobalScopes()
            ->where('tenant_id', $application->tenant_id)
            ->where('name', $application->discipline)
            ->value('id');
    }

    /**
     * Build a provider service zone from the wizard's polygon points (tolerant of the
     * {lat,lng} / {latitude,longitude} shapes). Needs ≥3 points to form a polygon.
     */
    private function createServiceZone(Provider $provider, ProviderApplication $application): void
    {
        $points = collect($application->service_zones ?? [])
            ->map(fn ($p): array => [
                'latitude' => $p['latitude'] ?? $p['lat'] ?? null,
                'longitude' => $p['longitude'] ?? $p['lng'] ?? null,
            ])
            ->filter(fn ($p): bool => filled($p['latitude']) && filled($p['longitude']))
            ->map(fn ($p): array => ['latitude' => (float) $p['latitude'], 'longitude' => (float) $p['longitude']])
            ->values();

        if ($points->count() < 3) {
            return;
        }

        $ring = $points->map(fn ($p): array => [$p['longitude'], $p['latitude']])->all();
        $ring[] = $ring[0];

        $provider->serviceZones()->create([
            'name' => null,
            'polygon_geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [$ring]]),
            'bbox_north' => $points->pluck('latitude')->max(),
            'bbox_south' => $points->pluck('latitude')->min(),
            'bbox_east' => $points->pluck('longitude')->max(),
            'bbox_west' => $points->pluck('longitude')->min(),
            'is_active' => true,
        ]);
    }

    /**
     * Move each uploaded credential file into the provider's folder and create a
     * pending credential record (verification_status unverified → surfaces in the
     * Credential Review queue; status pending_review per the application flow).
     */
    private function importCredentials(Provider $provider, ProviderApplication $application): void
    {
        foreach ($application->credential_uploads ?? [] as $upload) {
            $sourcePath = $upload['path'] ?? null;

            if ($sourcePath === null) {
                continue;
            }

            $destPath = "provider-credentials/{$provider->id}/".basename($sourcePath);

            if (Storage::exists($sourcePath)) {
                Storage::move($sourcePath, $destPath);
            }

            ProviderCredential::create([
                'provider_id' => $provider->id,
                'document_type_id' => $upload['document_type_id'] ?? null,
                'file_path' => $destPath,
                'status' => 'pending_review',
                'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
            ]);
        }
    }

    private function inviteProvider(ProviderApplication $application): void
    {
        $tenant = Tenant::find($application->tenant_id);

        if (! $tenant instanceof Tenant || blank($application->email)) {
            return;
        }

        $invitation = $tenant->invitations()->create([
            'email' => $application->email,
            'token' => Str::random(40),
            'status' => InvitationStatus::PENDING->value,
            'role' => [TenancyPermissionConstants::ROLE_SP_PROVIDER],
        ]);

        $this->tenants->handleAfterInvitationCreated($invitation);
    }
}
