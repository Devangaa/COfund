<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyDeadlineApproaching extends Command
{
    protected $signature = 'campaign:notify-deadline';
    protected $description = 'Send deadline approaching notifications to backers (H-3 and H-1)';

    public function handle(): void
    {
        $now = Carbon::now();
        $h3 = $now->copy()->addDays(3)->startOfDay();
        $h1 = $now->copy()->addDay()->startOfDay();

        $notifications = [];

        $campaignsH3 = Campaign::where('status', 'active')
            ->whereDate('deadline', $h3)
            ->get();

        foreach ($campaignsH3 as $campaign) {
            $backerIds = $campaign->backings()->distinct()->pluck('user_id');
            foreach ($backerIds as $userId) {
                $notifications[] = [
                    'user_id' => $userId,
                    'type' => 'deadline_approaching',
                    'title' => "Deadline dekat: {$campaign->title}",
                    'body' => "Kampanye Anda \"{$campaign->title}\" akan berakhir dalam 3 hari. Sisa hari ini: {$this->remainingDays($campaign)} hari.",
                    'data' => json_encode([
                        'campaign_id' => $campaign->id,
                        'campaign_slug' => $campaign->slug,
                        'days_remaining' => $this->remainingDays($campaign),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $campaignsH1 = Campaign::where('status', 'active')
            ->whereDate('deadline', $h1)
            ->get();

        foreach ($campaignsH1 as $campaign) {
            $backerIds = $campaign->backings()->distinct()->pluck('user_id');
            foreach ($backerIds as $userId) {
                $notifications[] = [
                    'user_id' => $userId,
                    'type' => 'deadline_approaching',
                    'title' => "Deadline dekat: {$campaign->title}",
                    'body' => "Kampanye Anda \"{$campaign->title}\" akan berakhir besok! Sisa hari ini: {$this->remainingDays($campaign)} hari. Jadwalkan dukungan Anda sebelum deadline.",
                    'data' => json_encode([
                        'campaign_id' => $campaign->id,
                        'campaign_slug' => $campaign->slug,
                        'days_remaining' => $this->remainingDays($campaign),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($notifications)) {
            Notification::insert($notifications);
        }

        $this->info("Sent " . count($notifications) . " deadline approaching notifications.");
    }

    protected function remainingDays(Campaign $campaign): int
    {
        return (int) max(0, Carbon::now()->startOfDay()->diffInDays(Carbon::parse($campaign->deadline)->startOfDay()));
    }
}
