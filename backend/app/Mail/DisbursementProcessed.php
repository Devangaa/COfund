<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DisbursementProcessed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $creator,
        public Campaign $campaign,
        public float $disbursementAmount,
        public float $platformFee
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Dana Berhasil Dicairkan — {$this->campaign->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.disbursement',
        );
    }
}
