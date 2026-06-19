<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partial-staffing + referring/lead clinician fields for intake requests.
     *
     * lead_clinician_id is a plain unsignedBigInteger with NO foreign-key constraint:
     * SQL Server rejects FKs that create multiple cascade paths back to a shared
     * ancestor (sp_providers -> tenants), so all sp_* cross-references stay FK-less.
     */
    public function up(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table) {
            $table->string('referring_clinician_name')->nullable();
            $table->string('referring_clinician_phone')->nullable();
            $table->boolean('is_partial_staffing')->default(false);
            $table->string('assistant_clinician_name')->nullable();
            $table->unsignedBigInteger('lead_clinician_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table) {
            $table->dropColumn([
                'referring_clinician_name',
                'referring_clinician_phone',
                'is_partial_staffing',
                'assistant_clinician_name',
                'lead_clinician_id',
            ]);
        });
    }
};
