<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\ProviderSurvey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderSurveyRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProviderSurvey $survey) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('How was your recent therapy visit?'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staffpick.provider-survey-request',
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
