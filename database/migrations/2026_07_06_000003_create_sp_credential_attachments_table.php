<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof-of-credential attachment documents, stored as BLOBs directly in Azure SQL
 * (VARBINARY(MAX)) to keep the files inside the database's HIPAA BAA boundary — not on
 * the filesystem or a separate object store. One-to-many against sp_provider_credentials;
 * multiple attachments persist per credential across renewal cycles and uploads never
 * overwrite older files.
 *
 * Tenancy is inherited transitively (attachment -> credential -> provider -> tenant), so
 * there is no direct tenant_id column, matching sp_provider_credentials. Per the codebase
 * convention, provider_credential_id is a plain unsignedBigInteger with no FK constraint
 * (SQL Server rejects multiple cascade paths).
 *
 * Soft delete is a tombstone: deleted_at + deleted_by_user_id are set and the content BLOB
 * is cleared to reclaim storage, but the row and its metadata persist so there is always a
 * record that a document existed, who uploaded it, and who removed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_credential_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_credential_id');
            $table->binary('content')->nullable(); // VARBINARY(MAX); nulled on soft delete
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamp('uploaded_at');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_credential_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_credential_attachments');
    }
};
