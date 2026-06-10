<?php

namespace Database\Seeders;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds realistic demo providers and subjects around North Palm Beach, FL so the
 * matching engine has data to run against on staging. Idempotent (keyed on email);
 * ensures the default taxonomy exists first. Run with:
 *   php artisan db:seed --class=StaffPickTestDataSeeder
 */
class StaffPickTestDataSeeder extends Seeder
{
    private const TENANT_UUID = 'fcts';

    // North Palm Beach, FL.
    private const BASE_LAT = 26.82;

    private const BASE_LNG = -80.05;

    public function run(): void
    {
        $tenant = Tenant::query()->where('uuid', self::TENANT_UUID)->first();

        if ($tenant === null) {
            $this->command?->warn("StaffPickTestDataSeeder: no tenant '".self::TENANT_UUID."' — skipping.");

            return;
        }

        // Make sure disciplines + tiers exist before we reference them.
        app(TenantTaxonomySeeder::class)->seedForTenant($tenant);

        $disciplines = Discipline::query()->where('tenant_id', $tenant->id)->get()->keyBy('abbreviation');
        $tiers = ProviderTier::query()->where('tenant_id', $tenant->id)->get()->keyBy('name');

        $disciplineCycle = ['PT', 'OT', 'SLP'];
        $tierCycle = ['Gold', 'Silver', 'Platinum'];
        $genderCycle = ['female', 'male', 'female', 'male', 'non_binary'];
        $radiusCycle = [25, 20, 15, 10, 25];
        $firstNames = ['Avery', 'Jordan', 'Riley', 'Casey', 'Morgan', 'Quinn', 'Skyler', 'Reese', 'Devon', 'Harper', 'Emerson', 'Rowan', 'Sage', 'Parker', 'Finley'];

        foreach (range(0, 14) as $i) {
            $discipline = $disciplines->get($disciplineCycle[$i % 3]);
            $tier = $tiers->get($tierCycle[$i % 3]);

            Provider::updateOrCreate(
                ['tenant_id' => $tenant->id, 'email' => "provider{$i}@fcts.test"],
                [
                    'first_name' => $firstNames[$i],
                    'last_name' => 'Demo'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'discipline_id' => $discipline?->id,
                    'tier_id' => $tier?->id,
                    'latitude' => 26.70 + ($i * 0.017),
                    'longitude' => -80.12 + (($i % 5) * 0.03),
                    'radius_max_miles' => $radiusCycle[$i % 5],
                    'radius_preferred_miles' => max(5, $radiusCycle[$i % 5] - 5),
                    'gender' => $genderCycle[$i % 5],
                    'status' => 'active',
                    'is_active' => true,
                    'is_preferred' => in_array($i, [2, 9], true),
                    'internal_rating' => $i % 3 === 0 ? 4.50 : null,
                    'rating_90day_avg' => $i % 4 === 0 ? 4.20 : null,
                ],
            );
        }

        $subjects = [
            ['Maria', 'Garcia', 'es-pref', 'language_preference' => 'Spanish'],
            ['James', 'Wright', 'female-pref', 'provider_gender_preference' => 'female'],
            ['Linda', 'Nguyen', 'plain'],
            ['Robert', 'Patel', 'plain'],
            ['Susan', 'Brooks', 'plain'],
        ];

        foreach ($subjects as $i => $data) {
            Subject::updateOrCreate(
                ['tenant_id' => $tenant->id, 'email' => "subject{$i}@fcts.test"],
                array_merge([
                    'first_name' => $data[0],
                    'last_name' => $data[1],
                    'phone' => '561555'.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                    'latitude' => self::BASE_LAT + (($i - 2) * 0.02),
                    'longitude' => self::BASE_LNG + (($i - 2) * 0.02),
                    'is_active' => true,
                    'language_preference' => $data['language_preference'] ?? null,
                    'provider_gender_preference' => $data['provider_gender_preference'] ?? null,
                ]),
            );
        }

        $this->command?->info('Seeded 15 demo providers and 5 subjects for North Palm Beach, FL.');
    }
}
