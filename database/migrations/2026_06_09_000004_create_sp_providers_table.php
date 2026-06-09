<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Identity
            $table->string('first_name');
            $table->string('last_name');
            $table->string('business_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('phone_alt', 30)->nullable();

            // Address
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Classification
            $table->unsignedBigInteger('discipline_id')->nullable();
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->boolean('is_contractor')->default(true);

            // Matching preferences
            $table->unsignedInteger('radius_preferred_miles')->default(15);
            $table->unsignedInteger('radius_max_miles')->default(25);

            // Demographics (for patient preference matching)
            $table->string('gender', 20)->nullable();

            // Status
            $table->string('status')->default('active'); // active, inactive, pending
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason')->nullable();

            // Payroll
            $table->string('payroll_id')->nullable();
            $table->string('tax_id')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('discipline_id');
            $table->index('tier_id');
            $table->index('office_id');
            $table->index('status');
        });

        // Provider ↔ Specialty pivot
        Schema::create('sp_provider_specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('specialty_id');
            $table->timestamps();

            $table->unique(['provider_id', 'specialty_id']);
        });

        // Provider ↔ Language pivot
        Schema::create('sp_provider_languages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('language_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['provider_id', 'language_id']);
        });

        // Provider service zones (polygon-based geographic zones)
        Schema::create('sp_provider_service_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->string('name')->nullable();
            // Polygon stored as GeoJSON string; SQL Server doesn't support native geometry easily
            $table->text('polygon_geojson')->nullable();
            // Bounding box for fast pre-filtering
            $table->decimal('bbox_north', 10, 7)->nullable();
            $table->decimal('bbox_south', 10, 7)->nullable();
            $table->decimal('bbox_east', 10, 7)->nullable();
            $table->decimal('bbox_west', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('provider_id');
        });

        // Provider credentials / document tracking
        Schema::create('sp_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('document_type_id');
            $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('valid'); // valid, expiring_soon, expired, missing
            $table->string('file_path')->nullable(); // stored file reference
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->timestamps();

            $table->index('provider_id');
            $table->index('document_type_id');
            $table->index('expires_at');
            $table->index('status');
        });

        // Provider availability windows
        Schema::create('sp_provider_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->tinyInteger('day_of_week'); // 0=Sun, 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_provider_availability');
        Schema::dropIfExists('sp_provider_credentials');
        Schema::dropIfExists('sp_provider_service_zones');
        Schema::dropIfExists('sp_provider_languages');
        Schema::dropIfExists('sp_provider_specialties');
        Schema::dropIfExists('sp_providers');
    }
};
