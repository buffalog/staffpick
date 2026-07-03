<?php

namespace Database\Seeders;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use Illuminate\Database\Seeder;

/**
 * Demo geocoordinates so the matching engine produces results. The engine reads the
 * case location from the SUBJECT (sp_subjects.latitude/longitude — see MatchingEngine)
 * and the provider location from sp_providers.latitude/longitude; intake requests hold
 * no coordinates. Columns are latitude/longitude (not lat/lng).
 *
 * Idempotent: re-running just re-applies the same values. Tenant 1 (FCTS).
 *
 * WARNING — DEMO ONLY. Do NOT run this against real case data.
 * 1. The sweep (bottom of run()) stamps EVERY tenant-1 subject that lacks
 *    coordinates with the Orlando fallback (28.5383, -81.3792). For a real
 *    patient that can be ~50 miles off their actual address, which silently
 *    corrupts matching (wrong distances, wrong "in range" set).
 * 2. It writes via query-builder update(), so Subject::saving does NOT fire —
 *    the real geocoding hook is bypassed and no address is resolved.
 * To geocode a real subject's true address, re-save it through the dashboard
 * (the Subject edit page / the case → subject link) so the model hook runs.
 * This seeder is intentionally excluded from DatabaseSeeder (boot seeding).
 */
class GeocoordinateSeeder extends Seeder
{
    private const TENANT_ID = 1;

    /** Orlando-metro fallback for cases without specific demo coordinates. */
    private const FALLBACK = [28.5383, -81.3792];

    public function run(): void
    {
        // Provider: Alex Rivera (Orlando).
        Provider::where('id', 32)->update([
            'latitude' => self::FALLBACK[0],
            'longitude' => self::FALLBACK[1],
        ]);

        // Named demo cases — coords live on the subject, resolved via the intake's
        // reference number (stable demo identifier).
        $byReference = [
            'R-DEMO-001' => [28.5997, -81.3392], // Mateo Gomez — Winter Park
            'R-DEMO-002' => [28.2920, -81.4079], // Olivia Chen — Kissimmee
            'R-DEMO-003' => [28.6274, -81.3659], // Walter Brooks — Maitland
            'R-7EKLLK' => self::FALLBACK,         // explicit fallback (was out-of-state)
            'R-QNQU7W' => self::FALLBACK,         // explicit fallback (was out-of-state)
        ];

        foreach ($byReference as $reference => [$lat, $lng]) {
            $subjectId = IntakeRequest::query()
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_number', $reference)
                ->value('subject_id');

            if ($subjectId !== null) {
                Subject::where('id', $subjectId)->update(['latitude' => $lat, 'longitude' => $lng]);
            }
        }

        // Sweep: any remaining tenant-1 subject with no coordinates gets the fallback,
        // so every case is at least in range for the demo.
        Subject::query()
            ->where('tenant_id', self::TENANT_ID)
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->update([
                'latitude' => self::FALLBACK[0],
                'longitude' => self::FALLBACK[1],
            ]);
    }
}
