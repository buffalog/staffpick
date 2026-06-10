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
            // Manually set by an admin/scheduler (1.00–5.00); null = unrated.
            $table->decimal('internal_rating', 3, 2)->nullable();
            // Manual elevation flag — pinned above tier ordering in matching.
            $table->boolean('is_preferred')->default(false);
            // Rolling patient-survey averages + counts, written by the weekly job.
            $table->decimal('rating_90day_avg', 3, 2)->nullable();
            $table->decimal('rating_180day_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_survey_count_90day')->default(0);
            $table->unsignedInteger('rating_survey_count_180day')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn([
                'internal_rating',
                'is_preferred',
                'rating_90day_avg',
                'rating_180day_avg',
                'rating_survey_count_90day',
                'rating_survey_count_180day',
            ]);
        });
    }
};
