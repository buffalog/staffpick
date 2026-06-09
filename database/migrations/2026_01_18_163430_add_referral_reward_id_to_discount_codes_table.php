<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            // SQL Server cascade cycle: discount_codes already has discount_id FK.
            // Adding referral_reward_id with cascade creates a second cascade path.
            // Use nullOnDelete (safe since column is nullable) instead of cascade.
            $table->foreignId('referral_reward_id')->nullable()->after('discount_id')->constrained('referral_rewards')->nullOnDelete();
            $table->boolean('is_referral_reward')->default(false)->after('referral_reward_id');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropForeign(['referral_reward_id']);
            $table->dropColumn(['referral_reward_id', 'is_referral_reward']);
        });
    }
};
