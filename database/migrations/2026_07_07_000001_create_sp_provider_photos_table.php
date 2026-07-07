<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider profile photos stored as BLOBs in Azure SQL (VARBINARY(MAX)), the same
 * mechanism as credential attachments — kept inside the database's HIPAA BAA boundary
 * rather than the filesystem or an object store.
 *
 * A separate 1:1 table (unique provider_id) rather than a column on sp_providers so the
 * multi-megabyte BLOB never rides along on the frequent provider list/card queries.
 * Replace-in-place: uploading a new photo overwrites the single row, no history.
 *
 * Tenancy is inherited transitively (photo -> provider -> tenant); provider_id is a plain
 * unsignedBigInteger with no FK, per the codebase convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_provider_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->binary('content')->nullable(); // VARBINARY(MAX)
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_provider_photos');
    }
};
