<?php

namespace App\Http\Controllers;

use App\Models\StaffPick\SlackWebhookLog;
use App\Models\StaffPick\TenantConfig;
use App\Services\StaffPick\SlackInboundService;
use App\Services\StaffPick\SlackNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Public inbound Slack webhook: POST /webhooks/slack/{token}. The token resolves the
 * tenant; the request is authenticated by the Slack request signature (no CSRF). A
 * message containing the tenant's keyword creates a draft intake and a confirmation
 * is posted back. Signature-verified requests are recorded in sp_slack_webhook_logs
 * for audit; bot-authored and system messages are dropped first (see isIgnorableEvent).
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

        if (! $this->signatureValid($request, $config->slackSigningSecret(), $body)) {
            SlackWebhookLog::create([
                'tenant_id' => $config->tenant_id,
                'event_type' => $payload['type'] ?? ($payload['event']['type'] ?? null),
                'signature_valid' => false,
                'payload' => $body,
            ]);

            abort(403, 'Invalid Slack signature.');
        }

        // Drop our own (and any bot-authored) posts plus system messages (channel
        // joins/leaves, edits) before logging or processing. StaffPick's own
        // confirmation re-enters as a message event, so without this guard a
        // keyword in a confirmation could loop, and every post-back would flood
        // the audit log.
        if ($this->isIgnorableEvent($payload)) {
            return response('', 200);
        }

        // Everything past the signature check runs inside a guard: a processing
        // failure must NEVER 500 back to Slack. The Events API retries non-2xx
        // deliveries and DISABLES the subscription after repeated failures, so an
        // unhandled exception here would silently break inbound Slack for the tenant.
        // We ack with 200 and report() the error; the raw payload is captured on the
        // audit row below for root-causing.
        try {
            $log = SlackWebhookLog::create([
                'tenant_id' => $config->tenant_id,
                'event_type' => $payload['type'] ?? ($payload['event']['type'] ?? null),
                'signature_valid' => true,
                'payload' => $body,
            ]);

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
        } catch (Throwable $e) {
            report($e);
        }

        return response('', 200);
    }

    /**
     * Whether an event should be dropped without logging or processing: bot/app
     * authored messages (including StaffPick's own confirmation posts) and system
     * subtype messages (channel join/leave, edits). Genuine human messages have no
     * bot_id/app_id and no subtype, so they pass through.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isIgnorableEvent(array $payload): bool
    {
        if (($payload['type'] ?? null) !== 'event_callback') {
            return false;
        }

        $event = $payload['event'] ?? [];

        return filled($event['bot_id'] ?? null)
            || filled($event['app_id'] ?? null)
            || filled($event['subtype'] ?? null);
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
