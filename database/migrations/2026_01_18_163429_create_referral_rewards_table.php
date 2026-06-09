<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            // SQL Server cascade cycle: referral_id already cascades through referrals → users.
            // referrer_user_id stored as plain integer to avoid multiple cascade paths.
            $table->unsignedBigInteger('referrer_user_id');
            $table->string('reward_type');
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['referrer_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
