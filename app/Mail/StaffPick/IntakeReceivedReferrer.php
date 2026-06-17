<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation sent to the referral source after they submit an intake, so they
 * have the reference number on record.
 */
class IntakeReceivedReferrer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IntakeRequest $intake) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('We received your referral (:reference)', ['reference' => $this->intake->reference_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.intake-received-referrer',
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
