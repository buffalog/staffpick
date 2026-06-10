<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sp_provider_surveys', function (Blueprint $table) {
            // Opaque token for the public survey-response link. A plain (non-unique)
            // index — a 48-char random token makes collisions negligible and avoids
            // SQL Server's one-NULL-only UNIQUE-nullable constraint.
            $table->string('token')->nullable();
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_provider_surveys', function (Blueprint $table) {
            $table->dropIndex(['token']);
            $table->dropColumn('token');
        });
    }
};
