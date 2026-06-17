<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a provider when they receive an assignment offer. Contains NO PHI — only
 * discipline, general area (city), proposed start date, and the login-gated link to
 * review full details. The full case is only visible after login at /offers/{token}.
 */
class OfferAvailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AssignmentOffer $offer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New assignment offer'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.offer-available',
        );
    }
}
