<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant-per-user notification preferences (event × channel toggles) on the
 * tenant_user pivot. Nullable: the app treats a missing key as "true" (opt-out model),
 * which is the spec's "default all channels true" without enumerating a JSON default
 * for an event list that will grow. sp_tenant_configs (notify_push/email/sms) remains
 * the tenant-wide outer gate. The toggle UI comes in a later spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
