<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant Slack integration settings. The webhook URL drives outbound
     * notifications; the inbound token namespaces the public inbound webhook URL
     * (/webhooks/slack/{token}); the signing secret (falling back to the global
     * SLACK_SIGNING_SECRET) verifies inbound requests; the keyword triggers draft
     * intake creation from inbound messages.
     */
    public function up(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->string('slack_webhook_url')->nullable();
            $table->string('slack_signing_secret')->nullable();
            $table->string('slack_inbound_token')->nullable();
            $table->string('slack_intake_keyword')->default('new referral');

            $table->index('slack_inbound_token');
        });
    }

    public function down(): void
    {
        Schema::table('sp_tenant_configs', function (Blueprint $table) {
            $table->dropIndex(['slack_inbound_token']);
            $table->dropColumn(['slack_webhook_url', 'slack_signing_secret', 'slack_inbound_token', 'slack_intake_keyword']);
        });
    }
};
