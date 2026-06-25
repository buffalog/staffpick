<?php

use App\Models\StaffPick\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-provider identity color (#RRGGBB hex). Auto-assigned on creation via golden-angle
 * HSL (see Provider::booted) and overridable by staff on the provider form.
 *
 * Backfills existing providers here — golden-angle hue per tenant, ordered by id — so
 * the board and calendar are colored immediately, not only for providers created after
 * this migration. Plain nullable column, no unique constraint, so no filtered index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->string('color', 7)->nullable();
        });

        Provider::withoutGlobalScopes()
            ->whereNull('color')
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'color'])
            ->groupBy('tenant_id')
            ->each(function ($providers): void {
                $providers->values()->each(function (Provider $provider, int $index): void {
                    $provider->update(['color' => Provider::hslToHex(fmod($index * 137.508, 360), 65, 48)]);
                });
            });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }
};
