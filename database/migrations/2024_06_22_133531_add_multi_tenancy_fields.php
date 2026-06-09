<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL Server cascade cycle: these tables already have user_id FKs with cascades.
        // Adding tenant_id as a constrained FK would create multiple cascade paths.
        // Store as plain unsignedBigInteger to avoid the SQL Server cycle rejection.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
