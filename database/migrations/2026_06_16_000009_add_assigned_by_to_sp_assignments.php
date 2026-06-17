<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who created the assignment and when. For offer-accept this is the provider's
     * own user; for a manual assignment it's the scheduler. Plain column, no FK
     * (SQL Server cascade-path rule — CLAUDE.md).
     */
    public function up(): void
    {
        Schema::table('sp_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_assignments', function (Blueprint $table) {
            $table->dropColumn(['assigned_by_user_id', 'assigned_at']);
        });
    }
};
