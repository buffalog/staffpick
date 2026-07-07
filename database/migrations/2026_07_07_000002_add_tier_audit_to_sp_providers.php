<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight tier-change audit on the provider record (the same stamp pattern as the
 * credential-attachment soft deletes, not a full audit log): who changed the tier and when,
 * stamped on every change regardless of the role that made it.
 *
 * tier_source is added now but unused today — it exists so a future computed tier
 * (planned: derived from assignment response speed + credentialing timeliness) can tell a
 * manual override apart from a computed value and never silently overwrite the former. No
 * ->after() as SQL Server ignores column ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->unsignedBigInteger('tier_changed_by_user_id')->nullable();
            $table->timestamp('tier_changed_at')->nullable();
            $table->string('tier_source')->default('manual'); // manual | computed (future)
        });
    }

    public function down(): void
    {
        Schema::table('sp_providers', function (Blueprint $table) {
            $table->dropColumn(['tier_changed_by_user_id', 'tier_changed_at', 'tier_source']);
        });
    }
};
