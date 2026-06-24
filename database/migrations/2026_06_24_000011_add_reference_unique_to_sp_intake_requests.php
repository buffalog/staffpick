<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make the database the final arbiter of intake reference-number uniqueness within
 * a tenant. IntakeSubmissionService::generateReferenceNumber() already loops until
 * it finds a free candidate; this filtered unique index closes the race window if
 * two concurrent submissions generate the same R-XXXXXX. Filtered on non-null +
 * not-soft-deleted so blank refs and tombstoned rows don't collide.
 *
 * Filtered-index DDL: dblib guard below. Railway-validated only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->isLocalFreeTds() || DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('CREATE UNIQUE INDEX sp_intake_requests_reference_unique ON sp_intake_requests (tenant_id, reference_number) WHERE reference_number IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        if ($this->isLocalFreeTds() || DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS sp_intake_requests_reference_unique ON sp_intake_requests');
    }

    private function isLocalFreeTds(): bool
    {
        return DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'dblib';
    }
};
