<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional "requested provider" on an intake: a referral source can name a specific
 * clinician on the public intake form. The matching engine surfaces them first.
 * Plain unsignedBigInteger, no FK (SQL Server cascade-path rules, like the other
 * sp_* cross-references).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('requested_provider_id')->nullable()->after('lead_clinician_id');
        });
    }

    public function down(): void
    {
        Schema::table('sp_intake_requests', function (Blueprint $table): void {
            $table->dropColumn('requested_provider_id');
        });
    }
};
