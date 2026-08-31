<?php

namespace App\Mail;

use App\Models\Backing;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $backer,
        public Campaign $campaign,
        public Backing $backing
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Konfirmasi Backing — {$this->campaign->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.backing-confirmation',
        );
    }
}
