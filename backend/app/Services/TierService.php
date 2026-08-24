<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignTier;
use Illuminate\Support\Facades\DB;

class TierService
{
    public function __construct(protected CampaignService $campaignService) {}

    public function create(Campaign $campaign, array $data): CampaignTier
    {
        $this->campaignService->ensureEditable($campaign);

        return CampaignTier::create([
            'campaign_id' => $campaign->id,
            'name' => $data['name'],
            'min_amount' => $data['min_amount'],
            'quota' => $data['quota'],
            'remaining_quota' => $data['quota'],
            'reward_description' => $data['reward_description'] ?? null,
        ]);
    }

    public function update(Campaign $campaign, CampaignTier $tier, array $data): CampaignTier
    {
        $this->campaignService->ensureEditable($campaign);

        unset($data['remaining_quota']);

        $tier->update($data);
        return $tier;
    }

    public function deleteMany(Campaign $campaign, array $tierIds): void
    {
        $this->campaignService->ensureEditable($campaign);

        DB::transaction(function () use ($campaign, $tierIds) {
            $totalTiers = $campaign->tiers()->lockForUpdate()->count();
            if (($totalTiers - count($tierIds)) < 1) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Campaign must have at least 1 tier');
            }

            $validCount = $campaign->tiers()->whereIn('id', $tierIds)->count();
            if ($validCount !== count($tierIds)) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }

            $campaign->tiers()->whereIn('id', $tierIds)->delete();
        });
    }
}
