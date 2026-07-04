<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider ↔ Discipline pivot: providers can hold more than one discipline (e.g. a
 * dual-licensed OT/PT). Mirrors the sp_provider_languages pattern — plain columns, no
 * FK constraints (SQL Server multiple-cascade-path rule, see CLAUDE.md), unique on the
 * pair, plus an is_primary flag.
 *
 * The legacy sp_providers.discipline_id column is KEPT, not dropped: it continues to
 * point at the provider's PRIMARY discipline (the is_primary pivot row) so the many
 * direct readers (Slack notifications, offer SMS, the provider self-serve page load,
 * etc.) keep working unchanged. Provider::assignPrimaryDiscipline() keeps the two in
 * sync on every write. The column can be retired later once all readers move to the
 * disciplines() relationship.
 *
 * Backfill: every existing provider's discipline_id becomes one primary pivot row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_provider_disciplines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('discipline_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['provider_id', 'discipline_id']);
            $table->index('discipline_id');
        });

        $now = now();

        DB::table('sp_providers')
            ->whereNotNull('discipline_id')
            ->orderBy('id')
            ->select('id', 'discipline_id')
            ->chunk(500, function ($providers) use ($now): void {
                DB::table('sp_provider_disciplines')->insert(
                    $providers->map(fn ($provider): array => [
                        'provider_id' => $provider->id,
                        'discipline_id' => $provider->discipline_id,
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_provider_disciplines');
    }
};
