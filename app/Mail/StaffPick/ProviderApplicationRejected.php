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
 * Sent to an applicant when staff reject their provider application.
 */
class ProviderApplicationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProviderApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Update on your :tenant provider application', ['tenant' => $this->tenantName()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.provider-application-rejected',
            with: [
                'tenantName' => $this->tenantName(),
                'reason' => $this->application->rejection_reason,
            ],
        );
    }

    private function tenantName(): string
    {
        return Tenant::find($this->application->tenant_id)?->name ?? __('our');
    }
}
