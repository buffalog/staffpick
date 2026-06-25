<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider iCal calendar-feed token. calendar_token authenticates the public
 * /calendar/{tenant}/{token}.ics feed; nullable until a provider generates one.
 *
 * The unique index is FILTERED (WHERE calendar_token IS NOT NULL) via raw DDL — a
 * plain Blueprint unique would crash the deploy on SQL Server, which permits only ONE
 * NULL per unique index, and all existing providers start with a NULL token. Same
 * pattern as tenants_domain_unique and the collision-hardening indexes; dblib-guarded
 * for the local FreeTDS toolchain, sqlsrv-only on Railway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->string('calendar_token', 48)->nullable();
            $table->timestamp('calendar_token_generated_at')->nullable();
        });

        if ($this->isLocalFreeTds() || DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('CREATE UNIQUE INDEX sp_providers_calendar_token_unique ON sp_providers (calendar_token) WHERE calendar_token IS NOT NULL');
    }

    public function down(): void
    {
        if (! $this->isLocalFreeTds() && DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS sp_providers_calendar_token_unique ON sp_providers');
        }

        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->dropColumn(['calendar_token', 'calendar_token_generated_at']);
        });
    }

    private function isLocalFreeTds(): bool
    {
        return DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'dblib';
    }
};
