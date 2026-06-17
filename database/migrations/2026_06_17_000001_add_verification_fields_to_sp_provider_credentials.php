<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * License-verification tracking for credentials. verified_by_user_id already
     * exists on the table. verification_response holds the raw API payload for audit.
     */
    public function up(): void
    {
        Schema::table('sp_provider_credentials', function (Blueprint $table) {
            $table->string('license_number')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->string('verification_source')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('verification_response')->nullable();

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('sp_provider_credentials', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropColumn(['license_number', 'verification_status', 'verification_source', 'last_verified_at', 'verification_response']);
        });
    }
};
