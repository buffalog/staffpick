<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the per-provider identity color. It was dropped as a product decision — the
 * board card and Service Calendar no longer tint by assigned clinician. The column has
 * no default/index, so a plain dropColumn is safe on SQL Server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table): void {
            $table->string('color', 7)->nullable();
        });
    }
};
