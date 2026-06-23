<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\ProviderApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies tenant staff that a new provider application is awaiting review.
 */
class ProviderApplicationSubmittedStaff extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProviderApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New provider application: :name', ['name' => $this->application->fullName()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.provider-application-submitted-staff',
        );
    }
}
