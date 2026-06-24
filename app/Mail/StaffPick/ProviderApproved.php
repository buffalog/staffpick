<?php

namespace App\Mail\StaffPick;

use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Warm confirmation sent to a provider when staff approve their record.
 */
class ProviderApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Provider $provider, public Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You\'ve been approved with :tenant', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.staffpick.provider-approved',
        );
    }
}
