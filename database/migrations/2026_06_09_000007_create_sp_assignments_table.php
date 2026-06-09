<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assignment — links a provider to an intake request through the pipeline.
        // One intake request has one active assignment at a time, but history is preserved.
        Schema::create('sp_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('intake_request_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('tenant_id');

            // Assignment lifecycle
            // offered, accepted, declined, withdrawn, active, completed, cancelled
            $table->string('status')->default('offered');
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable(); // sequential offer window (300s default)
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('decline_reason_id')->nullable();
            $table->text('decline_notes')->nullable();
            $table->boolean('is_manual')->default(false); // true = scheduler manually assigned
            $table->boolean('is_current')->default(true); // false = historical record

            // Rate agreed for this assignment
            $table->decimal('rate', 8, 2)->nullable();
            $table->string('rate_type')->nullable(); // per_visit, hourly, flat

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('intake_request_id');
            $table->index('provider_id');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('is_current');
        });

        // Individual visit records within an assignment
        Schema::create('sp_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('intake_request_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('visit_type_id')->nullable();

            $table->date('visit_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('duration_hours', 4, 2)->nullable();

            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled, no_show
            $table->boolean('is_billable')->default(true);
            $table->decimal('bill_amount', 8, 2)->nullable();
            $table->decimal('pay_amount', 8, 2)->nullable();

            $table->string('emr_visit_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('assignment_id');
            $table->index('provider_id');
            $table->index('intake_request_id');
            $table->index('tenant_id');
            $table->index('visit_date');
        });

        // Assignment offer log — tracks the sequential offer queue per intake request
        Schema::create('sp_assignment_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('intake_request_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('tenant_id');

            $table->integer('offer_sequence'); // 1 = first offered, 2 = second, etc.
            $table->decimal('distance_miles', 6, 2)->nullable();
            $table->decimal('match_score', 8, 4)->nullable(); // weighted scoring engine output

            $table->timestamp('offered_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('response')->nullable(); // accepted, declined, expired, withdrawn
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('decline_reason_id')->nullable();

            $table->timestamps();

            $table->index('intake_request_id');
            $table->index('provider_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_assignment_offers');
        Schema::dropIfExists('sp_visits');
        Schema::dropIfExists('sp_assignments');
    }
};
