<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_versions ALTER COLUMN versionable_id TYPE bigint USING versionable_id::bigint');
            DB::statement('ALTER TABLE transaction_versions ALTER COLUMN versionable_id TYPE bigint USING versionable_id::bigint');
        } elseif (DB::connection()->getDriverName() === 'sqlsrv') {
            // SQL Server cannot alter a column that has a dependent index.
            // Must drop the index, change the column, then recreate it.
            DB::statement('DROP INDEX IF EXISTS subscription_versions_versionable_id_index ON subscription_versions');
            DB::statement('ALTER TABLE subscription_versions ALTER COLUMN versionable_id BIGINT NOT NULL');
            DB::statement('CREATE INDEX subscription_versions_versionable_id_index ON subscription_versions (versionable_id)');

            DB::statement('DROP INDEX IF EXISTS transaction_versions_versionable_id_index ON transaction_versions');
            DB::statement('ALTER TABLE transaction_versions ALTER COLUMN versionable_id BIGINT NOT NULL');
            DB::statement('CREATE INDEX transaction_versions_versionable_id_index ON transaction_versions (versionable_id)');
        } else {
            Schema::table('subscription_versions', function (Blueprint $table) {
                $table->unsignedBigInteger('versionable_id')->change();
            });

            Schema::table('transaction_versions', function (Blueprint $table) {
                $table->unsignedBigInteger('versionable_id')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_versions ALTER COLUMN versionable_id TYPE varchar(255)');
            DB::statement('ALTER TABLE transaction_versions ALTER COLUMN versionable_id TYPE varchar(255)');
        } elseif (DB::connection()->getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS subscription_versions_versionable_id_index ON subscription_versions');
            DB::statement('ALTER TABLE subscription_versions ALTER COLUMN versionable_id NVARCHAR(255) NOT NULL');
            DB::statement('CREATE INDEX subscription_versions_versionable_id_index ON subscription_versions (versionable_id)');

            DB::statement('DROP INDEX IF EXISTS transaction_versions_versionable_id_index ON transaction_versions');
            DB::statement('ALTER TABLE transaction_versions ALTER COLUMN versionable_id NVARCHAR(255) NOT NULL');
            DB::statement('CREATE INDEX transaction_versions_versionable_id_index ON transaction_versions (versionable_id)');
        } else {
            Schema::table('subscription_versions', function (Blueprint $table) {
                $table->string('versionable_id')->change();
            });

            Schema::table('transaction_versions', function (Blueprint $table) {
                $table->string('versionable_id')->change();
            });
        }
    }
};
