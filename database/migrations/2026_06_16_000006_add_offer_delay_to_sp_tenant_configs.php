<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seconds an offer stays open before it expires and the next provider in the
     * ranked queue is offered. Drives DispatchOffers / CheckOfferExpiry.
     */
    public function up(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->unsignedInteger('offer_delay_seconds')->default(300);
        });
    }

    public function down(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->dropColumn('offer_delay_seconds');
        });
    }
};
