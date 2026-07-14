<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a case escalated (see IntakeRequest::ESCALATION_* and MatchingEngine::blockingReason).
 *
 * Escalation used to say "provider pool exhausted" unconditionally, which is a lie for a
 * case the engine could never match in the first place — an ungeocoded subject, no
 * discipline. Staff went hunting for provider availability when the fix was the address.
 *
 * Read only while status = escalated, and escalate() always overwrites it, so it can never
 * go stale. No ->after(): column ordering is a MySQL-ism and the DB is Azure SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->string('escalation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->dropColumn('escalation_reason');
        });
    }
};
