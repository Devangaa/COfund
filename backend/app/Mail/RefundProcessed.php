<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundProcessed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $backer,
        public Campaign $campaign,
        public float $amount
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Dana Dikembalikan — {$this->campaign->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.refund',
        );
    }
}
