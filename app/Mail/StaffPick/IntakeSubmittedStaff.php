<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to tenant intake staff when a referral source submits a new intake.
 */
class IntakeSubmittedStaff extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IntakeRequest $intake) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New intake request: :reference', ['reference' => $this->intake->reference_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.intake-submitted-staff',
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
