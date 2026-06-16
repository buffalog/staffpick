<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports auto-save of the self-service onboarding wizard: the wizard persists a
     * 'draft' Provider as the clinician types and records which step they reached so a
     * return visit can resume in place. No FK (SQL Server cascade-path rule — CLAUDE.md).
     */
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            // 1-based index of the furthest wizard step reached, for resume-on-return.
            $table->unsignedTinyInteger('onboarding_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn('onboarding_step');
        });
    }
};
