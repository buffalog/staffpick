<?php

namespace App\Mail\StaffPick;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic scheduler/staff alert email (assignment accepted, no clinicians available,
 * etc.). Carries a heading, body, and an optional deep link to the case.
 */
class SchedulerAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $heading,
        public string $bodyText,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staffpick.scheduler-alert');
    }
}
