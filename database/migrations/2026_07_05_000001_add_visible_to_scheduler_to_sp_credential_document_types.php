<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduler-visibility gate for credential types. A false-flagged type is HR-only:
 * sp_staff (the Scheduler view) never sees credentials of that type. sp_hr, sp_admin
 * and super-admins see every type regardless of this flag.
 *
 * Default false is the safe default — a newly created or Other-promoted type is HR-only
 * until someone deliberately reclassifies it, so nothing HR-only is exposed by accident.
 * Plain ADD COLUMN with a default; no dependent index, so no SQL Server ALTER dance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_credential_document_types', function (Blueprint $table) {
            $table->boolean('visible_to_scheduler')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('sp_credential_document_types', function (Blueprint $table) {
            $table->dropColumn('visible_to_scheduler');
        });
    }
};
