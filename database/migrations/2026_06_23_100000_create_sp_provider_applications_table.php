<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table for the public provider self-serve onboarding wizard. An application
 * is a draft that a guest fills in over several steps (resumable via application_token)
 * and submits for staff review; on approval it's mapped into a real sp_providers row.
 *
 * tenant_id and reviewed_by are plain unsignedBigInteger (no FK) per the Azure SQL
 * cascade-path rules followed across the sp_* schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_provider_applications', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('tenant_id'); // no FK
            $table->string('application_token', 64)->unique(); // resume token
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected
            $table->string('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable(); // no FK
            $table->timestamp('reviewed_at')->nullable();

            // Identity
            $table->string('first_name');
            $table->string('last_name');
            // Not globally unique: the same person may apply to multiple tenants, and a
            // rejected (soft-deleted) application must not permanently block re-applying.
            // "One active application per tenant+email" is enforced in the controller.
            $table->string('email');
            $table->string('phone')->nullable();

            // Address
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Classification
            $table->string('discipline')->nullable();
            $table->string('gender')->nullable();
            $table->json('specialties')->nullable();
            $table->json('service_zones')->nullable(); // GeoJSON polygons
            $table->unsignedInteger('preferred_radius')->nullable();
            $table->unsignedInteger('maximum_radius')->nullable();
            $table->boolean('is_contractor')->default(true);

            // Credentials
            $table->json('credential_uploads')->nullable(); // file paths array

            // Progress
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('step_data')->nullable(); // full wizard state for resume

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_provider_applications');
    }
};
