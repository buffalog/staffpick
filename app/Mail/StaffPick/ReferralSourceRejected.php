<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\ReferralSource;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Respectful notice sent to a referral source when staff decline their
 * registration. Carries the rejection reason for the body.
 */
class ReferralSourceRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReferralSource $source, public Tenant $tenant, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Update on your registration with :tenant', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.referral-source-rejected',
            with: [
                // Render the human-readable label, not the stored key.
                'reasonLabel' => ReferralSource::rejectionReasonOptions()[$this->reason] ?? $this->reason,
            ],
        );
    }
}
