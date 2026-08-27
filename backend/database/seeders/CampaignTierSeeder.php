<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignTier;
use Illuminate\Database\Seeder;

class CampaignTierSeeder extends Seeder
{
    public function run(): void
    {
        $campaign1 = Campaign::where('slug', 'campaign-creator-1')->first();
        $campaign2 = Campaign::where('slug', 'campaign-creator-2')->first();

        CampaignTier::create([
            'campaign_id' => $campaign1->id,
            'name' => 'Early Bird',
            'min_amount' => 100000,
            'quota' => 10,
            'remaining_quota' => 10,
            'reward_description' => 'Early bird reward',
        ]);

        CampaignTier::create([
            'campaign_id' => $campaign1->id,
            'name' => 'Supporter',
            'min_amount' => 250000,
            'quota' => 20,
            'remaining_quota' => 20,
            'reward_description' => 'Supporter reward',
        ]);

        CampaignTier::create([
            'campaign_id' => $campaign2->id,
            'name' => 'Gold Backer',
            'min_amount' => 500000,
            'quota' => 5,
            'remaining_quota' => 5,
            'reward_description' => 'Gold backer reward',
        ]);
    }
}
