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
 * Warm confirmation sent to a referral source after they self-register.
 */
class ReferralSourceRegisteredApplicant extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReferralSource $source, public Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Thanks for registering with :tenant', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.referral-source-registered-applicant',
        );
    }
}
