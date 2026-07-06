<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service-zone permission flag: whether a provider is allowed to adjust their own
 * service zones. Previously staff recorded this as free text in Notes; promoted here
 * into a structured boolean. No ->after() as SQL Server ignores column ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->boolean('can_adjust_own_service_zones')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn('can_adjust_own_service_zones');
        });
    }
};
