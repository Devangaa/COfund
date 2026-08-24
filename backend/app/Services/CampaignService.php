<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignImage;
use App\Models\CampaignTier;
use App\Models\CampaignUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CampaignService
{
    public function create(User $creator, array $data, array $images, array $tiers): Campaign
    {
        if ($creator->role !== 'creator') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Only creators can create campaigns');
        }

        return DB::transaction(function () use ($creator, $data, $images, $tiers) {
            $campaign = Campaign::create([
                'user_id' => $creator->id,
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'slug' => $data['slug'] ?? null,
                'description' => $data['description'],
                'target_amount' => $data['target_amount'],
                'deadline' => $data['deadline'],
                'video_url' => $data['video_url'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($images as $index => $file) {
                $path = $file->store('campaigns', 'public');
                CampaignImage::create([
                    'campaign_id' => $campaign->id,
                    'url' => $path,
                    'is_primary' => $index === 0,
                ]);
            }

            foreach ($tiers as $tierData) {
                CampaignTier::create([
                    'campaign_id' => $campaign->id,
                    'name' => $tierData['name'],
                    'min_amount' => $tierData['min_amount'],
                    'quota' => $tierData['quota'],
                    'remaining_quota' => $tierData['quota'],
                    'reward_description' => $tierData['reward_description'] ?? null,
                ]);
            }

            return $campaign->fresh(['images', 'tiers']);
        });
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $this->ensureEditable($campaign);
        $campaign->update($data);
        return $campaign;
    }

    public function submitForReview(Campaign $campaign): Campaign
    {
        $this->ensureEditable($campaign);
        $campaign->update(['status' => 'review']);
        return $campaign;
    }

    public function approve(Campaign $campaign, User $admin): Campaign
    {
        $campaign->update([
            'status' => 'active',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        return $campaign;
    }

    public function reject(Campaign $campaign, User $admin, string $note): Campaign
    {
        $campaign->update([
            'status' => 'draft',
            'rejection_note' => $note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        return $campaign;
    }

    public function forceFail(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => 'failed']);
        return $campaign;
    }

    public function destroy(Campaign $campaign): void
    {
        $this->ensureEditable($campaign);

        DB::transaction(function () use ($campaign) {
            foreach ($campaign->images as $image) {
                Storage::disk('public')->delete($image->url);
            }

            CampaignImage::where('campaign_id', $campaign->id)->delete();
            CampaignTier::where('campaign_id', $campaign->id)->delete();
            CampaignUpdate::where('campaign_id', $campaign->id)->delete();

            $campaign->delete();
        });
    }

    public function ensureEditable(Campaign $campaign): void
    {
        if ($campaign->status !== 'draft') {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Campaign can only be edited in draft status');
        }
    }
}
