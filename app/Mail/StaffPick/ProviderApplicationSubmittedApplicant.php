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
 * Confirmation to the applicant once they submit their application for review.
 */
class ProviderApplicationSubmittedApplicant extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProviderApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :tenant provider application has been received', ['tenant' => $this->tenantName()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.provider-application-submitted-applicant',
            with: ['tenantName' => $this->tenantName()],
        );
    }

    private function tenantName(): string
    {
        return Tenant::find($this->application->tenant_id)?->name ?? __('our');
    }
}
