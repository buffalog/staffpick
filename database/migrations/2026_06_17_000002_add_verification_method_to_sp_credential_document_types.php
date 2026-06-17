<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How each credential type is verified: 'api' (RapidAPI license lookup),
     * 'deep_link' (pre-filled state-board URL a staffer opens), or 'manual'
     * (document upload only). api_discipline + rapidapi_host configure the API call;
     * deep_link_url_template carries a {license_number} placeholder.
     */
    public function up(): void
    {
        Schema::table('sp_credential_document_types', function (Blueprint $table) {
            $table->string('verification_method')->default('manual');
            $table->string('api_discipline')->nullable();
            $table->string('deep_link_url_template')->nullable();
            $table->string('rapidapi_host')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_credential_document_types', function (Blueprint $table) {
            $table->dropColumn(['verification_method', 'api_discipline', 'deep_link_url_template', 'rapidapi_host']);
        });
    }
};
