<?php

namespace App\Listeners;

use App\Events\CampaignRejected;
use App\Mail\CampaignRejected as CampaignRejectedMail;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class HandleCampaignRejected
{
    public function handle(CampaignRejected $event): void
    {
        $campaign = $event->campaign;
        $creator = $campaign->creator;

        Notification::create([
            'user_id' => $creator->id,
            'type' => 'campaign_rejected',
            'title' => "Kampanye Anda ditolak: {$campaign->title}",
            'body' => "Kampanye \"{$campaign->title}\" Anda ditolak oleh admin. Catatan: " . ($campaign->rejection_note ?? 'Tanpa catatan'),
            'data' => json_encode([
                'campaign_id' => $campaign->id,
                'campaign_slug' => $campaign->slug,
                'rejection_note' => $campaign->rejection_note,
            ]),
        ]);

        if ($creator->email_verified_at) {
            Mail::send(new CampaignRejectedMail($creator, $campaign));
        }
    }
}
