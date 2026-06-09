<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_referral_source_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::create('sp_referral_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('fax', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('portal_username')->nullable();
            $table->string('status')->default('active'); // active, inactive, delinquent
            $table->integer('billing_terms_days')->default(14);
            $table->unsignedBigInteger('group_id')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_referral_sources');
        Schema::dropIfExists('sp_referral_source_groups');
    }
};
