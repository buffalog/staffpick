<?php

namespace Database\Seeders;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Specialty;
use App\Models\StaffPick\Subject;
use Illuminate\Database\Seeder;

/**
 * Realistic case detail for the three demo cases (Mateo / Olivia / Walter). Idempotent
 * — keyed on intake reference_number.
 *
 * Field placement (verified against the schema + MatchingEngine):
 *  - Address, diagnosis, language_preference, provider_gender_preference → sp_subjects
 *    (the engine reads gender/language preference from the SUBJECT, not the intake).
 *  - frequency, visit_type, notes → sp_intake_requests (free-text string columns).
 *  - Specialty → sp_intake_request_specialties pivot (informational; NOT a match filter).
 *
 * "No preference" is stored as null, not the literal string, so the gender hard-filter
 * treats it as "any". Coordinates are intentionally left untouched (GeocoordinateSeeder).
 */
class DemoCaseDetailSeeder extends Seeder
{
    private const TENANT_ID = 1;

    public function run(): void
    {
        $cases = [
            'R-DEMO-001' => [
                'subject' => [
                    'address' => '847 Fairbanks Ave', 'city' => 'Winter Park', 'state' => 'FL', 'zip' => '32789',
                    'diagnosis' => 'Left hemiplegia following ischemic stroke (ICD-10: G81.94)',
                    'provider_gender_preference' => null,
                    'language_preference' => 'Spanish',
                ],
                'intake' => [
                    'frequency' => '3x/week',
                    'visit_type' => 'Evaluation',
                    'notes' => 'Patient requires home visit. Caregiver present during sessions. Limited English proficiency -- Spanish-speaking clinician strongly preferred.',
                ],
                'specialty' => 'Neurology', // closest existing specialty to "Neurological Rehabilitation"
            ],
            'R-DEMO-002' => [
                'subject' => [
                    'address' => '2214 Cypress Rd', 'city' => 'Kissimmee', 'state' => 'FL', 'zip' => '34741',
                    'diagnosis' => 'Right total knee arthroplasty, 6 weeks post-op (ICD-10: Z96.651)',
                    'provider_gender_preference' => 'female',
                    'language_preference' => 'English',
                ],
                'intake' => [
                    'frequency' => '2x/week',
                    'visit_type' => 'Treatment',
                    'notes' => 'Patient recently discharged from inpatient rehab. Requires gait training and ROM exercises. Prefers female clinician.',
                ],
                'specialty' => 'Orthopaedics', // closest existing specialty to "Orthopedic Rehabilitation"
            ],
            'R-DEMO-003' => [
                'subject' => [
                    'address' => '118 Lake Shore Dr', 'city' => 'Maitland', 'state' => 'FL', 'zip' => '32751',
                    'diagnosis' => 'Lumbar disc herniation with radiculopathy (ICD-10: M51.16)',
                    'provider_gender_preference' => null,
                    'language_preference' => 'English',
                ],
                'intake' => [
                    'frequency' => '2x/week',
                    'visit_type' => 'Re-evaluation',
                    'notes' => 'Long-term patient returning after symptom recurrence. Previously treated by same clinician -- lead clinician reassignment preferred.',
                ],
                'specialty' => null, // No preference
            ],
        ];

        foreach ($cases as $reference => $data) {
            $intake = IntakeRequest::query()
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_number', $reference)
                ->first();

            if ($intake === null) {
                continue;
            }

            if ($intake->subject_id !== null) {
                Subject::where('id', $intake->subject_id)->update($data['subject']);
            }

            IntakeRequest::where('id', $intake->id)->update($data['intake']);

            $specialtyId = $data['specialty'] === null ? null : Specialty::query()
                ->where('tenant_id', self::TENANT_ID)
                ->where('name', $data['specialty'])
                ->value('id');

            // sync (not attach) keeps the seeder idempotent and clears Walter's set.
            $intake->specialties()->sync($specialtyId !== null ? [$specialtyId] : []);
        }

        $this->seedFemaleProvider();
    }

    /**
     * A female PT near Kissimmee so Olivia Chen (who prefers a female clinician — a hard
     * match filter) has an eligible provider. Alex Rivera is male, so without her Olivia
     * would have zero matches. Idempotent via email.
     */
    private function seedFemaleProvider(): void
    {
        Provider::updateOrCreate(
            ['tenant_id' => self::TENANT_ID, 'email' => 'priya.sharma@staffpick.dev'],
            [
                'first_name' => 'Priya',
                'last_name' => 'Sharma',
                'phone' => '407-555-0188',
                'gender' => 'female',
                'discipline_id' => 1, // Physical Therapy (matches Olivia's case)
                'tier_id' => 2,       // Silver
                'address' => '1320 W Oak St', 'city' => 'Kissimmee', 'state' => 'FL', 'zip' => '34741',
                'latitude' => 28.3100, 'longitude' => -81.4000, // ~1.5 mi from Olivia
                'radius_preferred_miles' => 15,
                'radius_max_miles' => 25,
                'status' => Provider::STATUS_ACTIVE,
                'is_active' => true,
            ],
        );
    }
}
