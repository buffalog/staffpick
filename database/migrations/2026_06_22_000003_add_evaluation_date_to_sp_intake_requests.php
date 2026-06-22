<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scheduled evaluation date for a case. Load-bearing for scheduling, reports,
 * and billing, and the event date for the provider's My Cases calendar. Nullable
 * (not every intake has been scheduled yet); plain column, no FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->date('evaluation_date')->nullable()->after('lead_clinician_id');
        });
    }

    public function down(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->dropColumn('evaluation_date');
        });
    }
};
