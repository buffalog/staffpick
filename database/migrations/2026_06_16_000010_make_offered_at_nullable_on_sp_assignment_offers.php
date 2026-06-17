<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offers are now created queued (ranked) and only stamped with offered_at when
     * actually sent, so the column must allow null. No dependent index on offered_at,
     * so the ALTER COLUMN is safe on SQL Server.
     */
    public function up(): void
    {
        Schema::table('sp_assignment_offers', function (Blueprint $table) {
            $table->timestamp('offered_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sp_assignment_offers', function (Blueprint $table) {
            $table->timestamp('offered_at')->nullable(false)->change();
        });
    }
};
