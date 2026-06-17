<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offer-pipeline fields: lifecycle status (pending until sent/responded), the
     * channel the offer was delivered through, and an opaque token for the
     * login-gated /offers/{token} accept/decline link.
     */
    public function up(): void
    {
        Schema::table('sp_assignment_offers', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->string('delivery_channel')->nullable();
            $table->string('token')->nullable();

            $table->index('status');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::table('sp_assignment_offers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['token']);
            $table->dropColumn(['status', 'delivery_channel', 'token']);
        });
    }
};
