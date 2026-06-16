<?php

namespace App\Services\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\TenantConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a qualifying inbound Slack message into a draft IntakeRequest. A message
 * qualifies when it contains the tenant's configured keyword (default "new
 * referral"). The draft carries the raw message text in its notes for an intake
 * coordinator to complete; a minimal placeholder Subject satisfies the not-null
 * subject relationship.
 */
class SlackInboundService
{
    public function __construct(
        private IntakeSubmissionService $intakeSubmissions,
    ) {}

    /**
     * Create a draft intake from an inbound message, or return null if the message
     * doesn't contain the trigger keyword.
     */
    public function createDraftFromMessage(TenantConfig $config, string $text, ?string $channel = null): ?IntakeRequest
    {
        if (! Str::contains($text, $config->slackIntakeKeyword(), ignoreCase: true)) {
            return null;
        }

        $tenantId = (int) $config->tenant_id;

        return DB::transaction(function () use ($tenantId, $text, $channel): IntakeRequest {
            $subject = Subject::create([
                'tenant_id' => $tenantId,
                'first_name' => 'Slack',
                'last_name' => 'Referral',
                'is_active' => true,
            ]);

            return IntakeRequest::create([
                'tenant_id' => $tenantId,
                'reference_number' => $this->intakeSubmissions->generateReferenceNumber($tenantId),
                'subject_id' => $subject->id,
                'status' => 'draft',
                'slack_channel_id' => $channel,
                'notes' => $text,
            ]);
        });
    }
}
