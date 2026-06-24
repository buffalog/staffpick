<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One provider per (tenant, email). Filtered on non-null email + not-soft-deleted:
 * email is nullable (so multiple null-email providers are allowed) and a deleted
 * provider shouldn't block re-adding the same email. Backs the application-layer
 * guards in ProviderApplicationService::submit() and the approve() pre-check in
 * ProviderApplicationReviewService.
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

        // Existing data may already hold duplicate (tenant_id, email) live providers,
        // which would abort the unique-index creation. Reversibly soft-delete the
        // extras first — per group keep the active-est, then oldest row; hide the
        // rest by stamping deleted_at (recoverable: NULL it back out). Only touches
        // rows that actually collide on the index's filter (email NOT NULL, live).
        DB::statement(<<<'SQL'
            WITH ranked AS (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY tenant_id, email
                    ORDER BY is_active DESC, id ASC
                ) AS rn
                FROM sp_providers
                WHERE deleted_at IS NULL AND email IS NOT NULL
            )
            UPDATE sp_providers
            SET deleted_at = SYSUTCDATETIME()
            WHERE id IN (SELECT id FROM ranked WHERE rn > 1)
        SQL);

        DB::statement('CREATE UNIQUE INDEX sp_providers_tenant_email_unique ON sp_providers (tenant_id, email) WHERE email IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        if ($this->isLocalFreeTds() || DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS sp_providers_tenant_email_unique ON sp_providers');
    }

    private function isLocalFreeTds(): bool
    {
        return DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'dblib';
    }
};
