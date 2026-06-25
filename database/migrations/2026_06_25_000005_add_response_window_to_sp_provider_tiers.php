<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tier response-window minutes, tenant-configurable so windows change without a
 * deploy.
 *
 * Also corrects the tier PRIORITY data to match the names (Platinum=1 best … Bronze=4)
 * and creates Bronze where missing — the seeded data had Gold=1/Silver=2/Platinum=3 and
 * no Bronze. Windows are then seeded BY PRIORITY (1→120, 2→60, 3→45, 4→30), not by name,
 * so the mapping is unambiguous: a tier gets 120 because it's priority 1, not because
 * it's named "Platinum". Scoring derives tier_rank from priority, never the name.
 *
 * The priority correction matches by name once here, to fix existing rows;
 * TenantTaxonomySeeder is updated to the same values so new tenants are born correct.
 */
return new class extends Migration
{
    /** One-time name → correct priority fix for existing rows. */
    private const PRIORITIES = ['Platinum' => 1, 'Gold' => 2, 'Silver' => 3, 'Bronze' => 4];

    /** Response window minutes by priority (priority 1 = best = longest window). */
    private const WINDOWS = [1 => 120, 2 => 60, 3 => 45, 4 => 30];

    public function up(): void
    {
        Schema::table('sp_provider_tiers', function (Blueprint $table): void {
            $table->unsignedInteger('response_window_minutes')->nullable();
        });

        // 1. Correct priorities so they match the names.
        foreach (self::PRIORITIES as $name => $priority) {
            DB::table('sp_provider_tiers')->where('name', $name)->update(['priority' => $priority]);
        }

        // 2. Create Bronze for any tenant that has tiers but no Bronze yet.
        foreach (DB::table('sp_provider_tiers')->distinct()->pluck('tenant_id') as $tenantId) {
            $hasBronze = DB::table('sp_provider_tiers')
                ->where('tenant_id', $tenantId)
                ->where('name', 'Bronze')
                ->exists();

            if (! $hasBronze) {
                DB::table('sp_provider_tiers')->insert([
                    'tenant_id' => $tenantId,
                    'name' => 'Bronze',
                    'priority' => 4,
                    'color' => '#CD7F32',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Seed windows BY PRIORITY — unambiguous going forward.
        foreach (self::WINDOWS as $priority => $minutes) {
            DB::table('sp_provider_tiers')->where('priority', $priority)->update(['response_window_minutes' => $minutes]);
        }
    }

    public function down(): void
    {
        Schema::table('sp_provider_tiers', function (Blueprint $table): void {
            $table->dropColumn('response_window_minutes');
        });
    }
};
