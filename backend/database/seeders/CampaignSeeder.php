<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\User;
use App\Models\CampaignImage;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $creator1 = User::where('email', 'zaki2@test.com')->first();
        $creator2 = User::where('email', 'zaki3@test.com')->first();
        $category = \App\Models\Category::first();

        $campaign1 = Campaign::create([
            'user_id' => $creator1->id,
            'category_id' => $category->id,
            'title' => 'Campaign Creator 1',
            'slug' => 'campaign-creator-1',
            'description' => 'Campaign milik creator pertama untuk testing backing',
            'target_amount' => 5000000,
            'collected_amount' => 0,
            'deadline' => now()->addDays(30),
            'status' => 'active',
        ]);

        CampaignImage::create([
            'campaign_id' => $campaign1->id,
            'url' => 'https://via.placeholder.com/150',
            'is_primary' => true,
        ]);

        $campaign2 = Campaign::create([
            'user_id' => $creator2->id,
            'category_id' => $category->id,
            'title' => 'Campaign Creator 2',
            'slug' => 'campaign-creator-2',
            'description' => 'Campaign milik creator kedua untuk testing backing',
            'target_amount' => 10000000,
            'collected_amount' => 0,
            'deadline' => now()->addDays(60),
            'status' => 'active',
        ]);

        CampaignImage::create([
            'campaign_id' => $campaign2->id,
            'url' => 'https://via.placeholder.com/150',
            'is_primary' => true,
        ]);
    }
}
