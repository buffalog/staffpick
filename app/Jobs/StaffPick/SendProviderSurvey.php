<?php

namespace App\Jobs\StaffPick;

use App\Mail\StaffPick\ProviderSurveyRequest;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\ProviderSurvey;
use App\Services\StaffPick\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Creates a patient satisfaction survey for a completed assignment and sends it
 * over SMS (preferred) or email. Idempotent — one survey per assignment.
 */
class SendProviderSurvey implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $assignmentId) {}

    public function handle(SmsService $sms): void
    {
        $assignment = Assignment::with('intakeRequest.subject')->find($this->assignmentId);

        if ($assignment === null) {
            return;
        }

        // One survey per assignment, even if completion fires more than once.
        if (ProviderSurvey::query()->where('assignment_id', $assignment->id)->exists()) {
            return;
        }

        $subject = $assignment->intakeRequest?->subject;

        if ($subject === null) {
            return;
        }

        $channel = match (true) {
            filled($subject->phone) => ProviderSurvey::CHANNEL_SMS,
            filled($subject->email) => ProviderSurvey::CHANNEL_EMAIL,
            default => null,
        };

        $survey = ProviderSurvey::create([
            'tenant_id' => $assignment->tenant_id,
            'assignment_id' => $assignment->id,
            'provider_id' => $assignment->provider_id,
            'subject_id' => $subject->id,
            'delivery_channel' => $channel,
            'status' => ProviderSurvey::STATUS_PENDING,
            'token' => Str::random(48),
        ]);

        if ($channel === ProviderSurvey::CHANNEL_SMS) {
            $sent = $sms->send($subject->phone, $this->message($survey));
            $survey->update([
                'status' => $sent ? ProviderSurvey::STATUS_SENT : ProviderSurvey::STATUS_BOUNCED,
                'sent_at' => now(),
            ]);

            return;
        }

        if ($channel === ProviderSurvey::CHANNEL_EMAIL) {
            Mail::to($subject->email)->send(new ProviderSurveyRequest($survey));
            $survey->update(['status' => ProviderSurvey::STATUS_SENT, 'sent_at' => now()]);

            return;
        }

        // No contact method on file — nothing to deliver to.
        $survey->update(['status' => ProviderSurvey::STATUS_BOUNCED]);
    }

    private function message(ProviderSurvey $survey): string
    {
        return __('How was your recent therapy visit? Please rate your provider here: :url', [
            'url' => $survey->responseUrl(),
        ]);
    }
}
