<?php

use App\Constants\ReferralConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            // SQL Server rejects multiple FKs to the same table regardless of action.
            // referrer_user_id keeps the FK; referred_user_id stored as plain integer.
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('referred_user_id');
            $table->string('referral_code')->index();
            $table->string('status')->default(ReferralConstants::STATUS_PENDING);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['referrer_user_id', 'referred_user_id']);
            $table->index(['referrer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
