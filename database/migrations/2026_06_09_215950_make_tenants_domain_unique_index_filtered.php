<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * tenants.domain is a nullable column with a plain unique index
 * (tenants_domain_unique, from 2025_03_08_165548_add_domain_to_tenants_table).
 * SQL Server (and Postgres) enforce uniqueness across NULLs — only one row may
 * hold NULL — so a second domainless tenant collides on that index. Tenants are
 * identified by uuid and domain is optional (TenantCreationService never sets
 * it), which on SQL Server caps the platform at a single tenant.
 *
 * Replace the plain unique index with a filtered one (WHERE domain IS NOT NULL)
 * so any number of domainless tenants are allowed while non-null domains remain
 * unique. MySQL already treats NULLs as distinct in a unique index and does not
 * support filtered indexes, so its existing plain index already permits multiple
 * null domains and is left unchanged.
 *
 * NOTE: this migration is authored for Railway (real pdo_sqlsrv) validation. The
 * local PHP 8.5 FreeTDS (pdo_dblib) toolchain segfaults on the filtered-index
 * DDL, so it has not been — and cannot be — run locally.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->isLocalFreeTds()) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS tenants_domain_unique ON tenants');
            DB::statement('CREATE UNIQUE INDEX tenants_domain_unique ON tenants (domain) WHERE domain IS NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS tenants_domain_unique');
            DB::statement('CREATE UNIQUE INDEX tenants_domain_unique ON tenants (domain) WHERE domain IS NOT NULL');
        }

        // MySQL: NULLs are already distinct in a unique index and filtered indexes
        // are unsupported, so the existing plain unique index already allows
        // multiple null domains — no change required.
    }

    public function down(): void
    {
        if ($this->isLocalFreeTds()) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS tenants_domain_unique ON tenants');
            DB::statement('CREATE UNIQUE INDEX tenants_domain_unique ON tenants (domain)');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS tenants_domain_unique');
            DB::statement('CREATE UNIQUE INDEX tenants_domain_unique ON tenants (domain)');
        }

        // MySQL: unchanged in up(), so nothing to revert.
    }

    /**
     * The local dev box runs the `sqlsrv` connection through the FreeTDS
     * (pdo_dblib) driver, which segfaults the PHP process on the filtered-index
     * DDL. Detect it and skip — Railway runs Microsoft's real pdo_sqlsrv, where
     * the index is created normally. Reading the PDO driver name is a cheap
     * metadata call and does not trigger the crash.
     */
    private function isLocalFreeTds(): bool
    {
        return DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'dblib';
    }
};
