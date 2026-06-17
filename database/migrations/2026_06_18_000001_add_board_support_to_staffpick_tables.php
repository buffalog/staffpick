<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduler Kanban board support:
 *  - A composite (tenant_id, status) index on sp_intake_requests so the board's
 *    per-tenant, per-column query is covered (the table already has separate
 *    single-column indexes, but not the composite the board groups on).
 *  - A language_warning flag on sp_assignment_offers. The matching engine produces
 *    this per candidate (MatchingResult::$languageWarning) but it was never
 *    persisted; the board needs it to flag intakes whose offered providers have a
 *    spoken-language mismatch with the patient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'sp_intake_requests_tenant_status_index');
        });

        Schema::table('sp_assignment_offers', function (Blueprint $table) {
            $table->boolean('language_warning')->default(false)->after('match_score');
        });
    }

    public function down(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table) {
            $table->dropIndex('sp_intake_requests_tenant_status_index');
        });

        Schema::table('sp_assignment_offers', function (Blueprint $table) {
            $table->dropColumn('language_warning');
        });
    }
};
