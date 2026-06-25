<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Match/dispatch cascade state on a case (see MatchDispatchService).
 *
 * current_match_provider_id is the provider holding the in-flight offer; it's plain
 * unsignedBigInteger with no FK per the Azure SQL cascade-path convention. On
 * acceptance the engine promotes it to lead_clinician_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_match_provider_id')->nullable();
            $table->unsignedInteger('cascade_attempt')->default(0);
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('last_match_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'current_match_provider_id',
                'cascade_attempt',
                'escalated_at',
                'last_match_sent_at',
            ]);
        });
    }
};
