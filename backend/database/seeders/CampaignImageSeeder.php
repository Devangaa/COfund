<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Database\Seeder;

class CampaignImageSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = Campaign::all();

        foreach ($campaigns as $campaign) {
            $primaryUrl = 'https://picsum.photos/seed/' . $campaign->slug . '/600/400';

            CampaignImage::create([
                'campaign_id' => $campaign->id,
                'url' => $primaryUrl,
                'is_primary' => true,
            ]);

            $extraCount = in_array($campaign->status->value, ['active', 'success'], true) ? 1 : 0;

            for ($i = 1; $i <= $extraCount; $i++) {
                CampaignImage::create([
                    'campaign_id' => $campaign->id,
                    'url' => 'https://picsum.photos/seed/' . $campaign->slug . '-img-' . $i . '/600/400',
                    'is_primary' => false,
                ]);
            }
        }

        $this->command->info('✓ CampaignImageSeeder: Gambar kampanye berhasil dibuat (minimal 1 primary per campaign)');
    }
}
