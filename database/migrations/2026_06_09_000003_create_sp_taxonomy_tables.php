<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant-configurable discipline types (OT, PT, ST, etc.)
        Schema::create('sp_disciplines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('abbreviation', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable specialty types
        Schema::create('sp_specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Reference language table (shared, not tenant-specific)
        Schema::create('sp_languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique(); // ISO 639-1
            $table->timestamps();
        });

        // Tenant-configurable credential document types
        Schema::create('sp_credential_document_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->boolean('has_expiry')->default(true);
            $table->integer('expiry_warning_days')->default(30);
            $table->boolean('deactivate_on_expiry')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable insurance types
        Schema::create('sp_insurance_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable decline reasons
        Schema::create('sp_decline_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable on-hold reasons
        Schema::create('sp_on_hold_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable cancellation reasons
        Schema::create('sp_cancellation_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable visit types
        Schema::create('sp_visit_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Tenant-configurable clinician tiers (Gold, Silver, Platinum, etc.)
        Schema::create('sp_provider_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->unsignedInteger('priority')->default(0); // lower = higher priority in matching
            $table->string('color', 20)->nullable(); // UI display color
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_provider_tiers');
        Schema::dropIfExists('sp_visit_types');
        Schema::dropIfExists('sp_cancellation_reasons');
        Schema::dropIfExists('sp_on_hold_reasons');
        Schema::dropIfExists('sp_decline_reasons');
        Schema::dropIfExists('sp_insurance_types');
        Schema::dropIfExists('sp_credential_document_types');
        Schema::dropIfExists('sp_languages');
        Schema::dropIfExists('sp_specialties');
        Schema::dropIfExists('sp_disciplines');
    }
};
