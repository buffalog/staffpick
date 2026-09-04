<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets one audit row represent a repeated identical disclosure.
 *
 * The scheduler board polls every 30 seconds (wire:poll.30s), so a tab left open for a shift
 * wrote ~960 'listed' rows, each re-recording the same patients, to the same person, on the same
 * screen. That is ~86% of projected audit volume and it buries the signal the log exists to
 * surface.
 *
 * occurred_at KEEPS ITS MEANING: the FIRST occurrence. last_occurred_at is the most recent one.
 * "Accessed from 14:02 through 22:10, 960 times" is the useful disclosure record; advancing
 * occurred_at instead would destroy the start of the access window, which is usually the fact an
 * investigation actually wants.
 *
 * Both columns only ever move forward. That is enforced on the model (AuditEvent::booted),
 * which is what keeps this consistent with an append-only record rather than a hole in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_audit_events', function (Blueprint $table): void {
            $table->unsignedInteger('repeat_count')->default(1);
            $table->timestamp('last_occurred_at')->nullable();
        });

        // Existing rows each represent exactly one occurrence.
        DB::statement('UPDATE sp_audit_events SET last_occurred_at = occurred_at WHERE last_occurred_at IS NULL');

        // Separate statement: on Postgres, ALTER COLUMN ... SET NOT NULL cannot ride along with
        // the ADD COLUMN above, and the backfill has to land first or this fails.
        DB::statement('ALTER TABLE sp_audit_events ALTER COLUMN last_occurred_at SET NOT NULL');

        // Serves the collapse lookup: the newest 'listed' row for one actor in one tenant.
        DB::statement('CREATE INDEX sp_audit_events_collapse_lookup ON sp_audit_events (tenant_id, user_id, action, occurred_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sp_audit_events_collapse_lookup');

        Schema::table('sp_audit_events', function (Blueprint $table): void {
            $table->dropColumn(['repeat_count', 'last_occurred_at']);
        });
    }
};
