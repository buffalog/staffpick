<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\ProviderApplication;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the applicant after step 1, so they can resume their application later.
 */
class ProviderApplicationResumeLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProviderApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Continue your :tenant provider application', ['tenant' => $this->tenantName()]),
        );
    }

    public function content(): Content
    {
        $tenant = Tenant::find($this->application->tenant_id);

        return new Content(
            markdown: 'emails.staffpick.provider-application-resume-link',
            with: [
                'tenantName' => $this->tenantName(),
                'resumeUrl' => route('staffpick.application.resume', [
                    'tenantSlug' => $tenant?->uuid,
                    'token' => $this->application->application_token,
                ]),
            ],
        );
    }

    private function tenantName(): string
    {
        return Tenant::find($this->application->tenant_id)?->name ?? __('our');
    }
}
