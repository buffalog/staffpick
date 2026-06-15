<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each referral source gets an opaque `intake_token` that powers a public,
 * no-login submission link (/intake/{token}). The token resolves the source and
 * — through it — the tenant, exactly like the survey token.
 *
 * The uniqueness index is FILTERED (WHERE intake_token IS NOT NULL): SQL Server
 * and Postgres treat NULLs as equal in a plain unique index, so a second
 * tokenless source would collide. See the reusable pattern in
 * 2026_06_09_215950_make_tenants_domain_unique_index_filtered.php.
 *
 * Authored for Railway (real pdo_sqlsrv) validation. The local FreeTDS
 * (pdo_dblib) toolchain segfaults on filtered-index DDL, so the index is skipped
 * locally; token uniqueness is also enforced in PHP (ReferralSource::ensureIntakeToken).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_referral_sources', function (Blueprint $table): void {
            $table->string('intake_token')->nullable()->after('portal_username');
        });

        if ($this->isLocalFreeTds()) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['sqlsrv', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX sp_referral_sources_intake_token_unique ON sp_referral_sources (intake_token) WHERE intake_token IS NOT NULL');
        } elseif ($driver === 'mysql') {
            // MySQL treats NULLs as distinct in a unique index and lacks filtered
            // indexes, so a plain unique index already permits multiple nulls.
            DB::statement('CREATE UNIQUE INDEX sp_referral_sources_intake_token_unique ON sp_referral_sources (intake_token)');
        }
    }

    public function down(): void
    {
        if (! $this->isLocalFreeTds()) {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlsrv') {
                DB::statement('DROP INDEX IF EXISTS sp_referral_sources_intake_token_unique ON sp_referral_sources');
            } elseif (in_array($driver, ['pgsql', 'mysql'], true)) {
                DB::statement('DROP INDEX IF EXISTS sp_referral_sources_intake_token_unique');
            }
        }

        Schema::table('sp_referral_sources', function (Blueprint $table): void {
            $table->dropColumn('intake_token');
        });
    }

    /**
     * The local dev box runs the `sqlsrv` connection through FreeTDS (pdo_dblib),
     * which segfaults the PHP process on filtered-index DDL. Railway runs real
     * pdo_sqlsrv where the index is created normally.
     */
    private function isLocalFreeTds(): bool
    {
        return DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'dblib';
    }
};
