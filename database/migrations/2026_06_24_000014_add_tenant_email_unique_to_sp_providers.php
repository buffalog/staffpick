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
