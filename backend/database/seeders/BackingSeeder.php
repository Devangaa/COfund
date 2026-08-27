<?php

namespace Database\Seeders;

use App\Models\Backing;
use App\Models\Campaign;
use App\Models\CampaignTier;
use App\Models\User;
use Illuminate\Database\Seeder;

class BackingSeeder extends Seeder
{
    public function run(): void
    {
        $backer = User::where('email', 'zaki@test.com')->first();
        $campaign1 = Campaign::where('slug', 'campaign-creator-1')->first();
        $campaign2 = Campaign::where('slug', 'campaign-creator-2')->first();
        $creator2 = User::where('email', 'zaki3@test.com')->first();
        $tier = CampaignTier::where('name', 'Early Bird')->first();

        Backing::create([
            'user_id' => $backer->id,
            'campaign_id' => $campaign1->id,
            'tier_id' => $tier->id,
            'amount' => 100000,
            'status' => 'completed',
        ]);

        Backing::create([
            'user_id' => $backer->id,
            'campaign_id' => $campaign2->id,
            'tier_id' => null,
            'amount' => 50000,
            'status' => 'completed',
        ]);

        Backing::create([
            'user_id' => $creator2->id,
            'campaign_id' => $campaign1->id,
            'tier_id' => null,
            'amount' => 75000,
            'status' => 'completed',
        ]);

        $campaign1->increment('collected_amount', 100000);
        $campaign1->increment('collected_amount', 75000);
        $campaign2->increment('collected_amount', 50000);
    }
}
