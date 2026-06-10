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
        // Pending tier-change reviews raised by the weekly job when a provider's
        // patient rating crosses the tenant promotion/demotion thresholds.
        Schema::create('sp_provider_rating_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('provider_id');
            $table->string('review_type'); // promotion, demotion, flag
            $table->unsignedBigInteger('current_tier_id')->nullable();
            $table->unsignedBigInteger('suggested_tier_id')->nullable();
            $table->decimal('rating_90day_avg', 3, 2)->nullable();
            $table->decimal('rating_180day_avg', 3, 2)->nullable();
            $table->unsignedInteger('survey_count')->default(0);
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->string('status')->default('pending'); // pending, approved, dismissed
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('provider_id');
            $table->index('tenant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sp_provider_rating_reviews');
    }
};
