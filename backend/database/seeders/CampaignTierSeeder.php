<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignTier;
use Illuminate\Database\Seeder;

class CampaignTierSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = Campaign::all();

        $tierConfig = [
            'bantu-anak-pedalaman-tepukan' => [
                ['name' => 'Early Bird', 'min_amount' => 25000, 'quota' => 20, 'remaining_quota' => 20, 'reward_description' => 'Stiker kampanye + sertifikat'],
                ['name' => 'Supporter', 'min_amount' => 50000, 'quota' => 50, 'remaining_quota' => 50, 'reward_description' => 'Stiker + buku cerita anak Pedalaman'],
                ['name' => 'Gold Backer', 'min_amount' => 100000, 'quota' => 20, 'remaining_quota' => 20, 'reward_description' => 'Semua di atas + undangan video call dengan anak'],
            ],
            'beasiswa-digital-2026' => [
                ['name' => 'Pendukung', 'min_amount' => 50000, 'quota' => 0, 'remaining_quota' => 0, 'reward_description' => 'Akses update progres kampanye'],
                ['name' => 'Sponsor', 'min_amount' => 250000, 'quota' => 50, 'remaining_quota' => 50, 'reward_description' => 'Sertifikat sponsor + nama di website'],
            ],
            'energi-surya-terjangkau-untuk-desa' => [
                ['name' => 'Desa Mitra', 'min_amount' => 100000, 'quota' => 30, 'remaining_quota' => 30, 'reward_description' => 'Papan nama desa di panel surya'],
            ],
            'kitabisa-ayo' => [
                ['name' => 'Donatur', 'min_amount' => 10000, 'quota' => 0, 'remaining_quota' => 0, 'reward_description' => 'Akses penggunaan aplikasi premium selamanya'],
            ],
            'proyek-pengembangan-sekolah-dasar' => [
                ['name' => 'Wali Kelas', 'min_amount' => 50000, 'quota' => 0, 'remaining_quota' => 0, 'reward_description' => 'Nama di papan pengabdian kelas'],
                ['name' => 'Bapak/Ibu Guru', 'min_amount' => 100000, 'quota' => 15, 'remaining_quota' => 15, 'reward_description' => 'Semua di atas + foto bersama'],
            ],
            'qa-draft-campaign' => [
                ['name' => 'QA Draft Tier', 'min_amount' => 50000, 'quota' => 10, 'remaining_quota' => 10, 'reward_description' => 'Tier fixture for CRUD testing'],
            ],
        ];

        foreach ($campaigns as $campaign) {
            $config = $tierConfig[$campaign->slug] ?? $tierConfig['bantu-anak-pedalaman-tepukan'];

            foreach ($config as $tier) {
                CampaignTier::create([
                    'campaign_id' => $campaign->id,
                    'name' => $tier['name'],
                    'min_amount' => $tier['min_amount'],
                    'quota' => $tier['quota'],
                    'remaining_quota' => $tier['remaining_quota'],
                    'reward_description' => $tier['reward_description'],
                ]);
            }
        }

        $total = CampaignTier::count();
        $this->command->info("✓ CampaignTierSeeder: {$total} tier berhasil dibuat (minimal 1 per campaign)");
    }
}
