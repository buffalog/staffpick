<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the referral source when their referral has been assigned to a clinician.
 */
class AssignmentConfirmedReferrer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IntakeRequest $intake) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your referral :reference has been assigned', ['reference' => $this->intake->reference_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.assignment-confirmed-referrer',
        );
    }
}
