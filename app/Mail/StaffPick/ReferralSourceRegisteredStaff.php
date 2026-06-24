<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\ReferralSource;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to tenant staff when a referral source self-registers and needs review.
 */
class ReferralSourceRegisteredStaff extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReferralSource $source) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New referral source registration: :name', ['name' => $this->source->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.referral-source-registered-staff',
        );
    }
}
