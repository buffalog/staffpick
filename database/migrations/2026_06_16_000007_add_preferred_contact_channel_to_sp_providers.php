<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a provider prefers to receive assignment offers: email, sms, or portal
     * (in-app only). Defaults to email.
     */
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->string('preferred_contact_channel')->default('email');
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn('preferred_contact_channel');
        });
    }
};
