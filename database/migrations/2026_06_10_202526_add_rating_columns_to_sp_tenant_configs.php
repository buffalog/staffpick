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
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            // Rating floors used as hard filters in matching; null = no floor.
            $table->decimal('rating_internal_min', 3, 2)->nullable()->default(null);
            $table->decimal('rating_patient_min', 3, 2)->nullable()->default(null);
            // Thresholds + cadence for the weekly promotion/demotion review job.
            $table->decimal('rating_promotion_threshold', 3, 2)->default(4.50);
            $table->decimal('rating_demotion_threshold', 3, 2)->default(3.00);
            $table->unsignedInteger('rating_min_survey_count')->default(10);
            $table->string('rating_review_period')->default('quarterly'); // quarterly, biannual
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->dropColumn([
                'rating_internal_min',
                'rating_patient_min',
                'rating_promotion_threshold',
                'rating_demotion_threshold',
                'rating_min_survey_count',
                'rating_review_period',
            ]);
        });
    }
};
