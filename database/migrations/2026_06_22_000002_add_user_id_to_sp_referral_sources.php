<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a referral source to a user account so a signed-in referrer can be
 * resolved to their ReferralSource within a tenant.
 *
 * No FK constraint: a constrained user_id here would add another cascade path
 * back to users, which SQL Server rejects (multiple cascade paths). Plain
 * unsignedBigInteger, matching the tenant_id pattern used across all sp_* tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_referral_sources', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('sp_referral_sources', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });
    }
};
