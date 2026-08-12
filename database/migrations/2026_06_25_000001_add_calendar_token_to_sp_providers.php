<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider iCal calendar-feed token. calendar_token authenticates the public
 * /calendar/{tenant}/{token}.ics feed; nullable until a provider generates one.
 *
 * The unique index is PARTIAL (WHERE calendar_token IS NOT NULL) via raw DDL rather than a
 * Blueprint unique, matching tenants_domain_unique and the other collision-hardening
 * indexes — it keeps the "many NULLs, unique non-NULLs" shape explicit in the schema instead
 * of relying on the engine's NULL-distinctness default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->string('calendar_token', 48)->nullable();
            $table->timestamp('calendar_token_generated_at')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX sp_providers_calendar_token_unique ON sp_providers (calendar_token) WHERE calendar_token IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sp_providers_calendar_token_unique');

        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->dropColumn(['calendar_token', 'calendar_token_generated_at']);
        });
    }
};
