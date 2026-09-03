<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The column defaulted to 'pending', which the 2026-06-25 vocabulary remap retired. That
 * migration rewrote existing rows but left the DDL default behind, so any INSERT that omits
 * `status` still mints a value no case-list page, dashboard count or oldest-pending alert
 * matches on: the row saves cleanly and is then invisible everywhere but All Cases.
 *
 * 'draft' is the real vocabulary's equivalent starting point (IntakeRequest::STATUS_DRAFT)
 * and is what SlackInboundService already writes for an incomplete case.
 *
 * Raw DDL rather than ->change(): a default swap is one ALTER on Postgres, and ->change()
 * would restate the whole column definition (type, nullability) from a Blueprint that has to
 * mirror the original exactly or silently rewrite it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sp_intake_requests ALTER COLUMN status SET DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sp_intake_requests ALTER COLUMN status SET DEFAULT 'pending'");
    }
};
