<?php

namespace App\Jobs;

use App\Mail\CampaignUpdatePosted;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class NotifyBackersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected CampaignUpdate $update
    ) {}

    public function handle(): void
    {
        $campaign = $this->update->campaign;
        $notifications = [];

        foreach ($campaign->backings as $backing) {
            $notifications[] = [
                'user_id' => $backing->user_id,
                'type' => 'campaign_update',
                'title' => "Update baru: {$campaign->title}",
                'body' => "{$campaign->creator->name} memposting pembaruan: {$this->update->title}",
                'data' => json_encode([
                    'campaign_id' => $campaign->id,
                    'update_id' => $this->update->id,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($notifications)) {
            Notification::insert($notifications);
        }

        $this->sendEmails($campaign);
    }

    protected function sendEmails(Campaign $campaign): void
    {
        $backers = User::whereIn('id', $campaign->backings()->distinct()->pluck('user_id'))
            ->whereNotNull('email_verified_at')
            ->get();

        foreach ($backers as $backer) {
            Mail::to($backer->email)->queue(
                new CampaignUpdatePosted($backer, $campaign, $this->update)
            );
        }
    }
}