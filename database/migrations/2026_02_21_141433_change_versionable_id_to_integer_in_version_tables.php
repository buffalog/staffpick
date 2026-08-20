<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * mpociot/versionable ships versionable_id as a string. Every versioned model in this app
 * keys on a bigint, so widen the column to match and let index lookups compare like for
 * like instead of casting on every read.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subscription_versions ALTER COLUMN versionable_id TYPE bigint USING versionable_id::bigint');
        DB::statement('ALTER TABLE transaction_versions ALTER COLUMN versionable_id TYPE bigint USING versionable_id::bigint');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscription_versions ALTER COLUMN versionable_id TYPE varchar(255)');
        DB::statement('ALTER TABLE transaction_versions ALTER COLUMN versionable_id TYPE varchar(255)');
    }
};
