<?php

namespace App\Listeners;

use App\Events\BackingCreated;
use App\Mail\BackingConfirmation;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class HandleBackingCreated
{
    public function handle(BackingCreated $event): void
    {
        $campaign = $event->campaign;
        $backer = $event->backer;
        $backing = $event->backing;

        // Notifikasi ke backer (in-app)
        Notification::create([
            'user_id' => $backer->id,
            'type' => 'backing_confirmed',
            'title' => "Backing berhasil: {$campaign->title}",
            'body' => "Terima kasih! Backing sebesar Rp " . number_format($backing->amount, 0, ',', '.') . " untuk kampanye \"{$campaign->title}\" telah berhasil diproses.",
            'data' => json_encode([
                'campaign_id' => $campaign->id,
                'campaign_slug' => $campaign->slug,
                'backing_id' => $backing->id,
                'amount' => $backing->amount,
            ]),
        ]);

        // Notifikasi ke creator (in-app)
        $creator = $campaign->creator;
        Notification::create([
            'user_id' => $creator->id,
            'type' => 'new_backing',
            'title' => "Backing baru: {$campaign->title}",
            'body' => "Ada backing baru sebesar Rp " . number_format($backing->amount, 0, ',', '.') . " dari {$backer->name} untuk kampanye Anda.",
            'data' => json_encode([
                'campaign_id' => $campaign->id,
                'campaign_slug' => $campaign->slug,
                'backer_id' => $backer->id,
                'backing_id' => $backing->id,
                'amount' => $backing->amount,
            ]),
        ]);

        // Email ke backer (jika email terverifikasi)
        if ($backer->email_verified_at) {
            Mail::to($backer->email)->queue(new BackingConfirmation($backer, $campaign, $backing));
        }
    }
}
