<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make the database the final arbiter of intake reference-number uniqueness within a tenant.
 * IntakeSubmissionService::generateReferenceNumber() already loops until it finds a free
 * candidate; this partial unique index closes the race window if two concurrent submissions
 * generate the same R-XXXXXX. Filtered on non-null + not-soft-deleted so blank refs and
 * tombstoned rows don't collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX sp_intake_requests_reference_unique ON sp_intake_requests (tenant_id, reference_number) WHERE reference_number IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sp_intake_requests_reference_unique');
    }
};
