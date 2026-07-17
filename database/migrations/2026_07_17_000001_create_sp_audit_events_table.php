<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only HIPAA audit trail: who read or wrote which patient PHI, and when. The rows hold
 * PHI (old/new values in context), so this table is itself treated as PHI: tenant-stamped, long
 * retention, and access-gated to a compliance role (PR 2). There is no updated_at: events are
 * immutable, enforced at the model layer now and via restricted DB grants/triggers later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_audit_events', function (Blueprint $table) {
            $table->id();

            // Null for pre-tenant events (e.g. a failed login before a tenant is resolved).
            $table->unsignedBigInteger('tenant_id')->nullable();

            // Null for token/unauth/system actors.
            $table->unsignedBigInteger('user_id')->nullable();

            // Denormalized actor identity at event time, so the trail survives a user delete.
            $table->string('actor_label', 255);

            // created|updated|deleted|viewed|login|logout|login_failed
            $table->string('action', 40);

            $table->string('auditable_type', 255)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // The patient the event pertains to.
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // json: {changes:{field:{old,new}}} for writes, {route,...} for reads.
            $table->longText('context')->nullable();

            $table->timestamp('occurred_at')->index();

            // Insertion time only. NO updated_at: rows are immutable.
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index('subject_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_audit_events');
    }
};
