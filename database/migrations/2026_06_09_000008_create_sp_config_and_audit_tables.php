<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant-level matching and workflow configuration
        Schema::create('sp_tenant_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();

            // Matching parameters
            $table->unsignedInteger('default_radius_miles')->default(15);
            $table->unsignedInteger('feathering_miles')->default(2);
            $table->unsignedInteger('offer_window_seconds')->default(300);
            $table->boolean('auto_dispatch')->default(true);
            $table->boolean('intake_person_is_assigner')->default(true);
            $table->boolean('default_provider_is_contractor')->default(true);

            // Billing
            $table->unsignedInteger('billing_terms_days')->default(14);
            $table->string('week_ending_day', 10)->default('saturday');

            // Notification settings
            $table->boolean('notify_push')->default(true);
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_sms')->default(true);

            // Portal settings
            $table->boolean('referral_portal_enabled')->default(false);
            $table->boolean('show_booked_option_in_app')->default(false);

            $table->timestamps();

            $table->index('tenant_id');
        });

        // Audit log for intake request status changes
        Schema::create('sp_intake_request_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('intake_request_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event'); // status_changed, assigned, file_uploaded, note_added, etc.
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // flexible additional data
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('intake_request_id');
            $table->index('tenant_id');
            $table->index('occurred_at');
        });

        // Notifications queue for provider offers
        Schema::create('sp_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('recipient_type'); // provider, user, referral_source
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel'); // push, email, sms
            $table->string('event_type'); // offer_sent, assignment_confirmed, credential_expiring, etc.
            $table->unsignedBigInteger('intake_request_id')->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->text('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, skipped
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['recipient_type', 'recipient_id']);
            $table->index('status');
            $table->index('intake_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_notifications');
        Schema::dropIfExists('sp_intake_request_history');
        Schema::dropIfExists('sp_tenant_configs');
    }
};
