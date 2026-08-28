<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignUpdatePosted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $backer,
        public Campaign $campaign,
        public CampaignUpdate $update
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update Baru dari Kampanye: {$this->campaign->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.campaign-update',
        );
    }
}
