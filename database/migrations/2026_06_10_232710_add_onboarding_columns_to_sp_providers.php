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
        Schema::table('sp_providers', function (Blueprint $table) {
            // Links a self-onboarded provider profile to its owning user account.
            // Plain column, no FK (SQL Server cascade-path constraint — see CLAUDE.md).
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('years_experience')->nullable();
            // Admin review outcome for a rejected application.
            $table->string('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn(['user_id', 'years_experience', 'rejection_reason', 'submitted_at']);
        });
    }
};
