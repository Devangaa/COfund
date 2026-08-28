<?php

namespace Database\Seeders;

use App\Models\Backing;
use App\Models\Campaign;
use App\Models\CampaignTier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class BackingSeeder extends Seeder
{
    public function run(): void
    {
        $backer = User::where('email', 'adi@test.com')->first();
        $creator2 = User::where('email', 'citra@test.com')->first();
        $creator1 = User::where('email', 'budi@test.com')->first();

        $activeCampaign = Campaign::where('slug', 'bantu-anak-pedalaman-tepukan')->first();
        $successCampaign = Campaign::where('slug', 'beasiswa-digital-2026')->first();
        $failedCampaign = Campaign::where('slug', 'proyek-pengembangan-sekolah-dasar')->first();

        $tierEarlyBird = CampaignTier::where('name', 'Early Bird')
            ->where('campaign_id', $activeCampaign->id)->first();
        $tierSupporter = CampaignTier::where('name', 'Supporter')
            ->where('campaign_id', $activeCampaign->id)->first();

        $backings = [
            [
                'user_id' => $backer->id,
                'campaign_id' => $activeCampaign->id,
                'tier_id' => $tierEarlyBird->id,
                'amount' => 25000,
                'status' => 'completed',
            ],
            [
                'user_id' => $backer->id,
                'campaign_id' => $activeCampaign->id,
                'tier_id' => $tierSupporter->id,
                'amount' => 50000,
                'status' => 'completed',
            ],
            [
                'user_id' => $creator2->id,
                'campaign_id' => $activeCampaign->id,
                'tier_id' => null,
                'amount' => 75000,
                'status' => 'completed',
            ],
            [
                'user_id' => $creator2->id,
                'campaign_id' => $successCampaign->id,
                'tier_id' => null,
                'amount' => 100000,
                'status' => 'completed',
            ],
            [
                'user_id' => $creator1->id,
                'campaign_id' => $failedCampaign->id,
                'tier_id' => null,
                'amount' => 50000,
                'status' => 'completed',
            ],
        ];

        $totalCollected = [
            $activeCampaign->id => 0,
            $successCampaign->id => 0,
            $failedCampaign->id => 0,
        ];

        foreach ($backings as $backingData) {
            $backing = Backing::create($backingData);

            Transaction::create([
                'user_id' => $backing->user_id,
                'backing_id' => $backing->id,
                'campaign_id' => $backing->campaign_id,
                'type' => 'payment',
                'amount' => $backing->amount,
                'status' => 'success',
                'reference' => 'mock_payment_seed_' . $backing->id,
            ]);

            $totalCollected[$backing->campaign_id] += $backing->amount;

            $tier = $backing->tier_id ? CampaignTier::find($backing->tier_id) : null;
            if ($tier && $tier->remaining_quota > 0) {
                $tier->decrement('remaining_quota');
            }
        }

        $activeCampaign->update(['collected_amount' => $totalCollected[$activeCampaign->id]]);
        $successCampaign->update(['collected_amount' => $totalCollected[$successCampaign->id]]);
        $failedCampaign->update(['collected_amount' => $totalCollected[$failedCampaign->id]]);

        $count = Backing::count();
        $this->command->info("✓ BackingSeeder: {$count} backing + transaksi payment berhasil dibuat");
    }
}
