<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Safety net for assignment-offer token uniqueness. Str::random(48) collision
 * probability is negligible, but the /offers/{token} provider page authorizes off
 * the token, so a DB-enforced unique index is the backstop. Filtered on non-null
 * since the column is nullable (offers can exist before dispatch sets a token).
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

        DB::statement('CREATE UNIQUE INDEX sp_assignment_offers_token_unique ON sp_assignment_offers (token) WHERE token IS NOT NULL');
    }

    public function down(): void
    {
        if ($this->isLocalFreeTds() || DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS sp_assignment_offers_token_unique ON sp_assignment_offers');
    }

    private function isLocalFreeTds(): bool
    {
        return DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'dblib';
    }
};
