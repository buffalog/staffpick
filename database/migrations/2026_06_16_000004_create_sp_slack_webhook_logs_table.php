<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for inbound Slack webhook requests: every request is logged with
     * its signature-verification result and raw payload for debugging, plus the
     * intake request it created (if any). Plain tenant_id, no FK (SQL Server
     * cascade-path rule — CLAUDE.md). tenant_id is nullable so requests bearing an
     * unrecognised token are still recorded.
     */
    public function up(): void
    {
        Schema::create('sp_slack_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('event_type')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->unsignedBigInteger('intake_request_id')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_slack_webhook_logs');
    }
};
