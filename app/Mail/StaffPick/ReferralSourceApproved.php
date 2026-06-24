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
 * Welcoming confirmation sent to a referral source when staff approve their
 * registration.
 */
class ReferralSourceApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReferralSource $source, public Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your registration with :tenant has been approved', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.referral-source-approved',
        );
    }
}
