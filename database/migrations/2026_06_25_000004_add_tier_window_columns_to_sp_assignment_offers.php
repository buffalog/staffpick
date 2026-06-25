<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the provider's tier and the response window on each offer, so the offer
 * record is self-describing even if the tier's window is later retuned. expired_at
 * stamps when ProcessMatchTimeouts expires an unanswered offer.
 *
 * No `outcome` column — the existing `status` field (pending/accepted/declined/
 * expired/withdrawn) remains the single lifecycle source of truth; "rejected" maps
 * to `declined`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_assignment_offers', function (Blueprint $table): void {
            $table->string('tier_at_offer')->nullable();
            $table->unsignedInteger('response_window_minutes')->nullable();
            $table->timestamp('expired_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sp_assignment_offers', function (Blueprint $table): void {
            $table->dropColumn(['tier_at_offer', 'response_window_minutes', 'expired_at']);
        });
    }
};
