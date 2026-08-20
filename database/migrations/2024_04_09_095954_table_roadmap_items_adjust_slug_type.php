<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * roadmap_items.slug was created as a uuid column; RoadmapService writes human-readable
 * slugs into it, so widen it to varchar(255). Postgres needs the type change and the
 * NOT NULL assertion as separate ALTER COLUMN actions, and the uuid -> varchar cast has to
 * be spelled out with USING.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE roadmap_items ALTER COLUMN slug TYPE varchar(255) USING slug::varchar(255)');
        DB::statement('ALTER TABLE roadmap_items ALTER COLUMN slug SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE roadmap_items ALTER COLUMN slug TYPE uuid USING slug::uuid');
        DB::statement('ALTER TABLE roadmap_items ALTER COLUMN slug SET NOT NULL');
    }
};
