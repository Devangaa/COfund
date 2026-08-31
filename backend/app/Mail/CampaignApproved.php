<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $creator,
        public Campaign $campaign
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Kampanye Anda Disetujui — {$this->campaign->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.campaign-approved',
        );
    }
}
