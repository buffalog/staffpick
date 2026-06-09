<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The aggregate root. One per case/patient request.
        Schema::create('sp_intake_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('reference_number')->nullable(); // human-readable case ID
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('referral_source_id')->nullable();
            $table->unsignedBigInteger('discipline_id')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();

            // Assigner (the scheduler who owns this case)
            $table->unsignedBigInteger('assigner_user_id')->nullable();

            // Pipeline status
            // pending, matching, assigned_pending, active, on_hold, finished, cancelled, closed
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('on_hold_reason_id')->nullable();
            $table->unsignedBigInteger('cancellation_reason_id')->nullable();
            $table->text('status_notes')->nullable();

            // Service details
            $table->string('authorization_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('frequency')->nullable(); // e.g. "2x/week"
            $table->integer('visits_authorized')->nullable();
            $table->integer('visits_completed')->default(0);
            $table->string('visit_type')->nullable();

            // Matching parameters (copied from tenant config at time of intake, can be overridden)
            $table->unsignedInteger('radius_miles')->nullable();
            $table->boolean('manual_assignment')->default(false);

            // Flags
            $table->boolean('needs_emr_transition')->default(false);
            $table->boolean('paperwork_complete')->default(false);

            // External references
            $table->string('emr_id')->nullable(); // ID in external EMR system
            $table->string('slack_channel_id')->nullable(); // existing Slack workflow

            $table->text('notes')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('subject_id');
            $table->index('referral_source_id');
            $table->index('discipline_id');
            $table->index('assigner_user_id');
            $table->index('status');
            $table->index('office_id');
        });

        // Files attached to an intake request (orders, facesheets, etc.)
        Schema::create('sp_intake_request_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('intake_request_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('label')->nullable(); // "Order", "Facesheet", etc.
            $table->string('visibility')->default('internal'); // internal, referral_source
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index('intake_request_id');
        });

        // Specialties requested on an intake (can be multiple)
        Schema::create('sp_intake_request_specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('intake_request_id');
            $table->unsignedBigInteger('specialty_id');
            $table->timestamps();

            $table->unique(['intake_request_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_intake_request_specialties');
        Schema::dropIfExists('sp_intake_request_files');
        Schema::dropIfExists('sp_intake_requests');
    }
};
