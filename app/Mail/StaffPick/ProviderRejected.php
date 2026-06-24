<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Respectful notice sent to a provider when staff reject their record.
 */
class ProviderRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Provider $provider, public Tenant $tenant, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Update on your status with :tenant', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.provider-rejected',
            with: [
                'reasonLabel' => \App\Models\StaffPick\Provider::rejectionReasonOptions()[$this->reason] ?? $this->reason,
            ],
        );
    }
}
