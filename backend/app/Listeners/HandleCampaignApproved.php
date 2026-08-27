<?php

namespace App\Listeners;

use App\Events\CampaignApproved;
use App\Mail\CampaignApproved as CampaignApprovedMail;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class HandleCampaignApproved
{
    public function handle(CampaignApproved $event): void
    {
        $campaign = $event->campaign;
        $creator = $campaign->creator;

        Notification::create([
            'user_id' => $creator->id,
            'type' => 'campaign_approved',
            'title' => "Kampanye Anda disetujui: {$campaign->title}",
            'body' => "Selamat! Kampanye \"{$campaign->title}\" Anda telah disetujui oleh admin dan sekarang sudah aktif.",
            'data' => json_encode([
                'campaign_id' => $campaign->id,
                'campaign_slug' => $campaign->slug,
            ]),
        ]);

        if ($creator->email_verified_at) {
            Mail::send(new CampaignApprovedMail($creator, $campaign));
        }
    }
}
