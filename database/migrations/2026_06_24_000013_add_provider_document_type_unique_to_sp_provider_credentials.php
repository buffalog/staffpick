<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One credential per (provider, document type). document_type_id is NOT NULL and
 * the table has no soft deletes, so a plain unique index is correct — no filter
 * needed. The app-layer firstOrCreate guards (ManualCredential::create,
 * ProviderApplicationReviewService::importCredentials) avoid hitting this; the
 * index is the backstop. Blueprint unique is portable, so no dblib guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_provider_credentials', function (Blueprint $table): void {
            $table->unique(['provider_id', 'document_type_id'], 'sp_provider_credentials_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sp_provider_credentials', function (Blueprint $table): void {
            $table->dropUnique('sp_provider_credentials_type_unique');
        });
    }
};
