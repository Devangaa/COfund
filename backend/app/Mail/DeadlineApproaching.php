<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeadlineApproaching extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $backer,
        public Campaign $campaign,
        public int $daysRemaining
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Deadline Approaching: {$this->campaign->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.deadline-approaching',
        );
    }
}
