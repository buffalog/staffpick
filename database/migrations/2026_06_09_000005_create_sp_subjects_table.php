<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Identity
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 30)->nullable();
            $table->string('phone_alt', 30)->nullable();
            $table->string('alt_contact_name')->nullable();
            $table->string('alt_contact_phone', 30)->nullable();
            $table->string('alt_contact_relationship')->nullable();

            // Address
            $table->string('address')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Demographics
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('preferred_language')->nullable();

            // Medical
            $table->text('diagnosis')->nullable();
            $table->string('pcp_name')->nullable();
            $table->string('pcp_phone', 30)->nullable();

            // Insurance
            $table->unsignedBigInteger('insurance_type_id')->nullable();
            $table->string('insurance_id')->nullable();
            $table->string('insurance_group')->nullable();

            // Preferences (for matching)
            $table->string('provider_gender_preference', 20)->nullable();
            $table->string('language_preference')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('insurance_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_subjects');
    }
};
