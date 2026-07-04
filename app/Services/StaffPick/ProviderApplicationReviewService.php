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
        // Duplicate guard: a provider with this tenant+email already exists. Reject the
        // application and bail. This runs BEFORE the transaction so the rejection update
        // commits — throwing inside DB::transaction would roll it back along with everything
        // else. Mirrors the sp_providers_tenant_email_unique DB index (the final arbiter).
        $existing = Provider::withoutGlobalScopes()
            ->where('tenant_id', $application->tenant_id)
            ->where('email', $application->email)
            ->whereNull('deleted_at')
            ->first();

        if (filled($application->email) && $existing !== null) {
            $application->update([
                'status' => ProviderApplication::STATUS_REJECTED,
                'rejection_reason' => 'Duplicate — provider with this email already exists.',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            throw new \RuntimeException("Provider with email {$application->email} already exists in this tenant.");
        }

        $invitation = null;

        $provider = DB::transaction(function () use ($application, $reviewer, &$invitation): Provider {
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

            // Seed the disciplines pivot from the application's single discipline (public
            // applications capture one). Staff can add more on the provider form later.
            $provider->disciplines()->sync(array_filter([$provider->discipline_id]));
            $provider->assignPrimaryDiscipline();

            $this->createServiceZone($provider, $application);
            $this->importCredentials($provider, $application);

            $application->update([
                'status' => ProviderApplication::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            // Create the invitation row inside the transaction so an invite failure
            // rolls back the whole approval (no orphaned provider on retry).
            $invitation = $this->createInvitation($application, $reviewer);

            return $provider;
        });

        // Side effect (email/event) only after the approval is durably committed.
        if ($invitation !== null) {
            $this->tenants->handleAfterInvitationCreated($invitation);
        }

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

            ProviderCredential::firstOrCreate(
                [
                    'provider_id' => $provider->id,
                    'document_type_id' => $upload['document_type_id'] ?? null,
                ],
                [
                    'file_path' => $destPath,
                    'status' => 'pending_review',
                    'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
                ]
            );
        }
    }

    private function createInvitation(ProviderApplication $application, User $reviewer): ?Invitation
    {
        $tenant = Tenant::find($application->tenant_id);

        if (! $tenant instanceof Tenant || blank($application->email)) {
            return null;
        }

        // Mirrors CreateInvitation: uuid/token/expires_at/user_id are all required
        // (NOT NULL). The inviting user is the reviewer approving the application.
        return $tenant->invitations()->create([
            'uuid' => (string) Str::uuid(),
            'email' => $application->email,
            'token' => Str::random(60),
            'expires_at' => now()->addDays(7),
            'user_id' => $reviewer->id,
            'status' => InvitationStatus::PENDING->value,
            'role' => [TenancyPermissionConstants::ROLE_SP_PROVIDER],
        ]);
    }
}
