<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * StaffPick authentication framework storage:
 *  - users gains a platform-level super-admin flag and a google_id for social login.
 *  - sp_tenant_configs gains the per-tenant SSO configuration (provider, OAuth client
 *    credentials with the secret encrypted at rest, domain, and enabled/required toggles).
 *  - sp_auth_logs records every SSO / super-admin login attempt for audit.
 *
 * Follows the established SQL Server patterns: sp_auth_logs is not directly tenant-FK'd
 * (the sp_* tables avoid cascade-path FKs back to users/tenants); the encrypted secret
 * is a text column because ciphertext is longer than the plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
            $table->string('google_id')->nullable()->after('is_super_admin');
            $table->index('google_id');
        });

        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->string('sso_provider')->nullable();
            $table->string('sso_client_id')->nullable();
            $table->text('sso_client_secret')->nullable();
            $table->string('sso_domain')->nullable();
            $table->boolean('sso_enabled')->default(false);
            $table->boolean('sso_required')->default(false);
        });

        Schema::create('sp_auth_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type');
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('provider')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_auth_logs');

        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->dropColumn([
                'sso_provider',
                'sso_client_id',
                'sso_client_secret',
                'sso_domain',
                'sso_enabled',
                'sso_required',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropColumn(['is_super_admin', 'google_id']);
        });
    }
};
