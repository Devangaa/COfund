<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class CampaignUpdateService
{
    public function create(Campaign $campaign, array $data): CampaignUpdate
    {
        if ($campaign->status !== CampaignStatus::ACTIVE) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Campaign update can only be posted when campaign is active');
        }

        return DB::transaction(function () use ($campaign, $data) {
            $update = CampaignUpdate::create([
                'campaign_id' => $campaign->id,
                'title' => $data['title'],
                'content' => $data['content'],
            ]);

            $this->notifyBackers($campaign, $update);

            return $update;
        });
    }

    public function update(CampaignUpdate $update, array $data): CampaignUpdate
    {
        $update->update($data);
        return $update;
    }

    public function destroy(CampaignUpdate $update): void
    {
        $update->delete();
    }

    protected function notifyBackers(Campaign $campaign, CampaignUpdate $update): void
    {
        $backerIds = $campaign->backings()
            ->distinct()
            ->pluck('user_id');

        if ($backerIds->isEmpty()) {
            return;
        }

        $notifications = $backerIds->map(function ($userId) use ($campaign, $update) {
            return [
                'user_id' => $userId,
                'type' => 'campaign_update',
                'title' => "Update baru: {$campaign->title}",
                'body' => $update->title,
                'data' => json_encode([
                    'campaign_id' => $campaign->id,
                    'update_id' => $update->id,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        Notification::insert($notifications);
    }
}
