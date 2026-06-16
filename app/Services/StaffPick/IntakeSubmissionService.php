<?php

namespace App\Services\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Mail\StaffPick\IntakeReceivedReferrer;
use App\Mail\StaffPick\IntakeSubmittedStaff;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\ReferralSource;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Turns a referral source's public intake submission into a pending IntakeRequest
 * (plus its Subject) and notifies staff and the referrer. The public Livewire
 * form is a thin UI over this service so the logic stays testable.
 *
 * Data contract (flat array, as the form collects it):
 *  - Subject: first_name, last_name, date_of_birth, gender, email, phone,
 *    phone_alt, alt_contact_name, alt_contact_phone, alt_contact_relationship,
 *    address, address_2, city, state, zip, latitude, longitude,
 *    preferred_language, diagnosis, pcp_name, pcp_phone, insurance_type_id,
 *    insurance_id, insurance_group, provider_gender_preference, language_preference
 *  - Request: discipline_id, visit_type, frequency, start_date, end_date,
 *    visits_authorized, authorization_number, radius_miles, notes
 */
class IntakeSubmissionService
{
    /** Crockford-style base32 without ambiguous chars (no I/O/0/1). */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private GeocodingService $geocoder,
        private TenantPermissionService $permissions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(ReferralSource $source, array $data): IntakeRequest
    {
        $intake = DB::transaction(function () use ($source, $data): IntakeRequest {
            $subject = $this->createSubject($source, $data);

            $intake = IntakeRequest::create([
                'tenant_id' => $source->tenant_id,
                'reference_number' => $this->generateReferenceNumber((int) $source->tenant_id),
                'subject_id' => $subject->id,
                'referral_source_id' => $source->id,
                'discipline_id' => $data['discipline_id'] ?? null,
                'status' => 'pending',
                'visit_type' => $data['visit_type'] ?? null,
                'frequency' => $data['frequency'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'visits_authorized' => $data['visits_authorized'] ?? null,
                'authorization_number' => $data['authorization_number'] ?? null,
                'radius_miles' => $data['radius_miles'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $intake->specialties()->sync($data['specialty_ids'] ?? []);

            return $intake;
        });

        $this->notify($source, $intake);

        return $intake;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSubject(ReferralSource $source, array $data): Subject
    {
        [$latitude, $longitude] = $this->resolveCoordinates($data);

        return Subject::create([
            'tenant_id' => $source->tenant_id,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_alt' => $data['phone_alt'] ?? null,
            'alt_contact_name' => $data['alt_contact_name'] ?? null,
            'alt_contact_phone' => $data['alt_contact_phone'] ?? null,
            'alt_contact_relationship' => $data['alt_contact_relationship'] ?? null,
            'address' => $data['address'] ?? null,
            'address_2' => $data['address_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'pcp_name' => $data['pcp_name'] ?? null,
            'pcp_phone' => $data['pcp_phone'] ?? null,
            'insurance_type_id' => $data['insurance_type_id'] ?? null,
            'insurance_id' => $data['insurance_id'] ?? null,
            'insurance_group' => $data['insurance_group'] ?? null,
            'provider_gender_preference' => $data['provider_gender_preference'] ?? null,
            'language_preference' => $data['language_preference'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Use supplied coordinates (e.g. from the pin-drop map) when present,
     * otherwise geocode the address.
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
     * A short, professional reference (R-XXXXXX) that does not leak intake volume.
     * Uniqueness is checked per tenant; the alphabet excludes ambiguous glyphs.
     */
    public function generateReferenceNumber(int $tenantId): string
    {
        do {
            $candidate = 'R-'.collect(range(1, 6))
                ->map(fn (): string => self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)])
                ->implode('');
        } while (
            IntakeRequest::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('reference_number', $candidate)
                ->exists()
        );

        return $candidate;
    }

    private function notify(ReferralSource $source, IntakeRequest $intake): void
    {
        $staff = $this->staffRecipients($source);

        if ($staff->isNotEmpty()) {
            Notification::make()
                ->title(__('New intake request'))
                ->body(__(':source submitted intake :reference for review.', [
                    'source' => $source->name,
                    'reference' => $intake->reference_number,
                ]))
                ->sendToDatabase($staff);

            $staff
                ->filter(fn (User $user): bool => filled($user->email))
                ->each(fn (User $user) => Mail::to($user->email)->queue(new IntakeSubmittedStaff($intake)));
        }

        if (filled($source->email)) {
            Mail::to($source->email)->queue(new IntakeReceivedReferrer($intake));
        }
    }

    /**
     * Tenant admins, falling back to all tenant users when roles aren't
     * resolvable. Mirrors ProviderProfileService's reviewer resolution.
     *
     * @return Collection<int, User>
     */
    private function staffRecipients(ReferralSource $source): Collection
    {
        $tenant = Tenant::find($source->tenant_id);

        if (! $tenant) {
            return collect();
        }

        $users = $tenant->users()->get();

        try {
            $admins = $users->filter(fn (User $user): bool => in_array(
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
