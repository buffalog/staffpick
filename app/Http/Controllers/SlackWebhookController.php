<?php

namespace App\Http\Controllers;

use App\Models\StaffPick\SlackWebhookLog;
use App\Models\StaffPick\TenantConfig;
use App\Services\StaffPick\SlackInboundService;
use App\Services\StaffPick\SlackNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public inbound Slack webhook: POST /webhooks/slack/{token}. The token resolves the
 * tenant; the request is authenticated by the Slack request signature (no CSRF). A
 * message containing the tenant's keyword creates a draft intake and a confirmation
 * is posted back. Every request is recorded in sp_slack_webhook_logs for audit.
 */
class SlackWebhookController extends Controller
{
    /** Reject requests whose signed timestamp is older than this (replay guard). */
    private const MAX_TIMESTAMP_AGE = 300;

    public function __invoke(
        Request $request,
        string $token,
        SlackInboundService $inbound,
        SlackNotificationService $slack,
    ): Response|JsonResponse {
        $config = TenantConfig::withoutGlobalScopes()
            ->where('slack_inbound_token', $token)
            ->first();

        abort_if($config === null, 404);

        $body = $request->getContent();
        $payload = json_decode($body, true) ?: [];
        $signatureValid = $this->signatureValid($request, $config->slackSigningSecret(), $body);

        $log = SlackWebhookLog::create([
            'tenant_id' => $config->tenant_id,
            'event_type' => $payload['type'] ?? ($payload['event']['type'] ?? null),
            'signature_valid' => $signatureValid,
            'payload' => $body,
        ]);

        if (! $signatureValid) {
            abort(403, 'Invalid Slack signature.');
        }

        // Slack's one-time endpoint verification handshake.
        if (($payload['type'] ?? null) === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? null]);
        }

        if (($payload['type'] ?? null) === 'event_callback') {
            $event = $payload['event'] ?? [];
            $text = (string) ($event['text'] ?? '');
            $channel = $event['channel'] ?? null;

            $intake = $inbound->createDraftFromMessage($config, $text, $channel);

            if ($intake !== null) {
                $log->update(['intake_request_id' => $intake->id]);

                $slack->notifyText(
                    $config->tenant_id,
                    __('Draft intake :reference created from this message.', ['reference' => $intake->reference_number]),
                );
            }
        }

        return response('', 200);
    }

    /**
     * Verify the Slack request signature (v0 HMAC-SHA256 over the raw body), within
     * the replay window. See https://api.slack.com/authentication/verifying-requests-from-slack.
     */
    private function signatureValid(Request $request, ?string $secret, string $body): bool
    {
        if (blank($secret)) {
            return false;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (blank($timestamp) || blank($signature)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_TIMESTAMP_AGE) {
            return false;
        }

        $expected = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, $secret);

        return hash_equals($expected, $signature);
    }
}
