<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\StaffPick\Specialty;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persists a clinician's self-service onboarding profile across the wizard's steps,
 * and drives the admin review outcome (approve / reject). The Filament wizard page
 * is a thin UI over this service so the logic stays testable.
 */
class ProviderProfileService
{
    public function __construct(
        private GeocodingService $geocoder,
        private TenantPermissionService $permissions,
        private SlackNotificationService $slack,
    ) {}

    /**
     * Create or update the user's provider profile from the wizard data and submit
     * it for admin review (status → pending). Re-runnable: editing an existing
     * profile updates it in place and replaces its child records.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(Tenant $tenant, User $user, array $data): Provider
    {
        // Geocode before opening the transaction — it may make an HTTP call and we
        // never want to hold a DB transaction open across the network.
        [$latitude, $longitude] = $this->resolveCoordinates($data);

        $provider = DB::transaction(function () use ($tenant, $user, $data, $latitude, $longitude): Provider {
            $provider = Provider::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'business_name' => $data['business_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'zip' => $data['zip'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'discipline_id' => $this->primaryDisciplineIdFromData($data),
                    'years_experience' => $data['years_experience'] ?? null,
                    'radius_preferred_miles' => $data['radius_preferred_miles'] ?? 15,
                    'radius_max_miles' => $data['radius_max_miles'] ?? 25,
                    'status' => Provider::STATUS_PENDING,
                    'is_active' => false,
                    'submitted_at' => now(),
                ],
            );

            $this->syncDisciplines($provider, $data);
            $this->syncSpecialties($provider, $data);
            $this->syncLanguages($provider, $data);

            $this->replaceAvailability($provider, $data['availability'] ?? []);
            $this->replaceServiceZone($provider, $data);
            $this->upsertCredentials($provider, $data['credentials'] ?? []);

            return $provider;
        });

        // Side effects only after the profile is durably committed.
        $this->notifyReviewers($tenant, $user, $provider);
        $this->slack->notifyProviderProfileSubmitted($provider);

        return $provider;
    }

    /**
     * Persist the wizard's current state as a draft without validation or reviewer
     * notification. Drives auto-save: re-runnable on every change, tolerant of
     * partial/empty data, and records the step reached so a return visit resumes in
     * place. Never downgrades an already-submitted (pending/active/rejected) profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(Tenant $tenant, User $user, array $data, ?int $step = null): Provider
    {
        return DB::transaction(function () use ($tenant, $user, $data, $step): Provider {
            $provider = Provider::firstOrNew(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

            if (! $provider->exists || $provider->status === Provider::STATUS_DRAFT) {
                $provider->status = Provider::STATUS_DRAFT;
                $provider->is_active = false;
            }

            $provider->fill([
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'business_name' => $data['business_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? null,
                'latitude' => filled($data['latitude'] ?? null) ? (float) $data['latitude'] : null,
                'longitude' => filled($data['longitude'] ?? null) ? (float) $data['longitude'] : null,
                'discipline_id' => $this->primaryDisciplineIdFromData($data),
                'years_experience' => filled($data['years_experience'] ?? null) ? (int) $data['years_experience'] : null,
                'radius_preferred_miles' => filled($data['radius_preferred_miles'] ?? null) ? (int) $data['radius_preferred_miles'] : 15,
                'radius_max_miles' => filled($data['radius_max_miles'] ?? null) ? (int) $data['radius_max_miles'] : 25,
            ]);

            if ($step !== null) {
                $provider->onboarding_step = $step;
            }

            $provider->save();

            $this->syncDisciplines($provider, $data);
            $this->syncSpecialties($provider, $data);
            $this->syncLanguages($provider, $data);

            $this->replaceAvailability($provider, $data['availability'] ?? []);
            $this->replaceServiceZone($provider, $data);
            $this->upsertCredentials($provider, $data['credentials'] ?? []);

            return $provider;
        });
    }

    /**
     * Sync the provider's disciplines, then reconcile the primary. Accepts either the
     * multi-select discipline_ids[] or a legacy single discipline_id. IDs are validated
     * against the tenant's disciplines — the pivot has no FK and the payload is a
     * forgeable Livewire submission.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncDisciplines(Provider $provider, array $data): void
    {
        $ids = Discipline::query()
            ->where('tenant_id', $provider->tenant_id)
            ->whereIn('id', $this->disciplineIdsFromData($data))
            ->pluck('id')
            ->all();

        $provider->disciplines()->sync($ids);
        $provider->assignPrimaryDiscipline();
    }

    /**
     * Normalize a discipline selection to a de-duped list of ints, accepting the new
     * discipline_ids[] or a legacy single discipline_id.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function disciplineIdsFromData(array $data): array
    {
        $ids = $data['discipline_ids'] ?? (filled($data['discipline_id'] ?? null) ? [$data['discipline_id']] : []);

        return collect((array) $ids)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function primaryDisciplineIdFromData(array $data): ?int
    {
        return $this->disciplineIdsFromData($data)[0] ?? null;
    }

    /**
     * Sync the provider's specialties, carrying the clinician's write-in detail on the
     * pivot for the "Other (write in)" specialty when it is selected.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncSpecialties(Provider $provider, array $data): void
    {
        $requested = collect($data['specialties'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        // Never trust client-submitted IDs: keep only specialties that actually belong
        // to this tenant (the form is a Livewire payload an attacker can forge).
        $ids = Specialty::query()
            ->where('tenant_id', $provider->tenant_id)
            ->whereIn('id', $requested)
            ->pluck('id');

        $otherId = Specialty::otherId($provider->tenant_id);
        $note = $data['specialty_other_note'] ?? null;

        $payload = $ids->mapWithKeys(fn (int $id): array => [
            $id => ($otherId !== null && $id === $otherId) ? ['notes' => $note] : [],
        ])->all();

        $provider->specialties()->sync($payload);
    }

    /**
     * Sync the provider's spoken languages, validating the submitted IDs against the
     * shared sp_languages lookup. The pivot has no FK (SQL Server cascade-path limits),
     * so a forged Livewire payload could otherwise write arbitrary language IDs.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLanguages(Provider $provider, array $data): void
    {
        $requested = collect($data['languages'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        $valid = Language::query()->whereIn('id', $requested)->pluck('id')->all();

        $provider->languages()->sync($valid);
    }

    public function approve(Provider $provider): Provider
    {
        $provider->update([
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
            'rejection_reason' => null,
            'deactivated_at' => null,
        ]);

        return $provider;
    }

    public function reject(Provider $provider, string $reason): Provider
    {
        $provider->update([
            'status' => Provider::STATUS_REJECTED,
            'is_active' => false,
            'rejection_reason' => $reason,
        ]);

        return $provider;
    }

    /**
     * Use supplied coordinates when present, otherwise geocode the address.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: float|null, 1: float|null}
     */
    private function resolveCoordinates(array $data): array
    {
        if (filled($data['latitude'] ?? null) && filled($data['longitude'] ?? null)) {
            return [(float) $data['latitude'], (float) $data['longitude']];
        }

        $address = collect([$data['address'] ?? null, $data['city'] ?? null, $data['state'] ?? null, $data['zip'] ?? null])
            ->filter()
            ->implode(', ');

        $result = $this->geocoder->geocode($address);

        return [$result['lat'] ?? null, $result['lng'] ?? null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $windows
     */
    private function replaceAvailability(Provider $provider, array $windows): void
    {
        $provider->availability()->delete();

        foreach ($windows as $window) {
            if (blank($window['start_time'] ?? null) || blank($window['end_time'] ?? null)) {
                continue;
            }

            $provider->availability()->create([
                'day_of_week' => (int) ($window['day_of_week'] ?? 0),
                'start_time' => $window['start_time'],
                'end_time' => $window['end_time'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Replace the provider's service zone with a polygon built from the entered
     * vertices, deriving a bounding box for fast pre-filtering. Needs ≥3 points.
     *
     * @param  array<string, mixed>  $data
     */
    private function replaceServiceZone(Provider $provider, array $data): void
    {
        $provider->serviceZones()->delete();

        $points = collect($data['service_zone_points'] ?? [])
            ->filter(fn ($p) => filled($p['latitude'] ?? null) && filled($p['longitude'] ?? null))
            ->map(fn ($p) => ['latitude' => (float) $p['latitude'], 'longitude' => (float) $p['longitude']])
            ->values();

        if ($points->count() < 3) {
            return;
        }

        $ring = $points->map(fn ($p) => [$p['longitude'], $p['latitude']])->all();
        $ring[] = $ring[0]; // close the polygon ring

        $latitudes = $points->pluck('latitude');
        $longitudes = $points->pluck('longitude');

        $provider->serviceZones()->create([
            'name' => $data['service_zone_name'] ?? null,
            'polygon_geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [$ring]]),
            'bbox_north' => $latitudes->max(),
            'bbox_south' => $latitudes->min(),
            'bbox_east' => $longitudes->max(),
            'bbox_west' => $longitudes->min(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $credentials
     */
    private function upsertCredentials(Provider $provider, array $credentials): void
    {
        foreach ($credentials as $credential) {
            $documentTypeId = $credential['document_type_id'] ?? null;

            if (blank($documentTypeId) || blank($credential['file_path'] ?? null)) {
                continue;
            }

            ProviderCredential::updateOrCreate(
                ['provider_id' => $provider->id, 'document_type_id' => $documentTypeId],
                [
                    'file_path' => $credential['file_path'],
                    'document_number' => $credential['document_number'] ?? null,
                    'license_number' => $credential['license_number'] ?? null,
                    'expires_at' => $credential['expires_at'] ?? null,
                    'status' => 'valid',
                ],
            );
        }
    }

    private function notifyReviewers(Tenant $tenant, User $submitter, Provider $provider): void
    {
        $recipients = $this->reviewers($tenant)->reject(fn (User $user) => $user->id === $submitter->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(__('New clinician application'))
            ->body(__(':name submitted a provider profile for review.', [
                'name' => trim("{$provider->first_name} {$provider->last_name}"),
            ]))
            ->sendToDatabase($recipients);
    }

    /**
     * Tenant admins, falling back to all tenant users when roles aren't resolvable
     * (e.g. a freshly created tenant without role templates).
     *
     * @return Collection<int, User>
     */
    private function reviewers(Tenant $tenant): Collection
    {
        $users = $tenant->users()->get();

        try {
            $admins = $users->filter(fn (User $user) => in_array(
                TenancyPermissionConstants::ROLE_ADMIN,
                $this->permissions->getTenantUserRoles($tenant, $user),
                true,
            ));

            if ($admins->isNotEmpty()) {
                return $admins->values();
            }
        } catch (Throwable) {
            // Fall through to notifying all tenant users.
        }

        return $users->values();
    }
}
