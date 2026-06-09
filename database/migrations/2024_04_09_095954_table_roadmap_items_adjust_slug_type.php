<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL Server cannot alter a column that has a dependent index.
        // Must drop the index, change the column, then recreate the index.
        if (config('database.default') === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS roadmap_items_slug_unique ON roadmap_items');
            DB::statement('ALTER TABLE roadmap_items ALTER COLUMN slug NVARCHAR(255) NOT NULL');
            DB::statement('CREATE UNIQUE INDEX roadmap_items_slug_unique ON roadmap_items (slug)');
        } else {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->string('slug', 255)->unique('roadmap_items_slug_unique_idx')->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS roadmap_items_slug_unique ON roadmap_items');
            DB::statement('ALTER TABLE roadmap_items ALTER COLUMN slug NVARCHAR(36) NOT NULL');
            DB::statement('CREATE UNIQUE INDEX roadmap_items_slug_unique ON roadmap_items (slug)');
        } else {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->uuid('slug')->unique()->change();
            });
        }
    }
};
