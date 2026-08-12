<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Safety net for assignment-offer token uniqueness. Str::random(48) collision probability is
 * negligible, but the /offers/{token} provider page authorizes off the token, so a
 * DB-enforced unique index is the backstop. Filtered on non-null since the column is
 * nullable (offers can exist before dispatch sets a token).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX sp_assignment_offers_token_unique ON sp_assignment_offers (token) WHERE token IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sp_assignment_offers_token_unique');
    }
};
