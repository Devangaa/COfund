<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignUpdate;
use Illuminate\Database\Seeder;

class CampaignUpdateSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = Campaign::whereIn('status', ['active', 'success', 'review', 'failed'])->get();

        $updateTemplates = [
            'bantu-anak-pedalaman-tepukan' => [
                ['title' => 'Terima Kasih! Dana Sudah 50% Terkumpul', 'content' => 'Terima kasih banyak kepada semua donatur! Kami telah berhasil mengumpulkan 50% dari target dana. Buku-buku sekolah sudah dipesan dan akan dikirimkan minggu ini. Kami akan membagikan update langsung saat barang tiba di desa.'],
                ['title' => 'Buku dan Perlengkapan Tiba di Desa', 'content' => 'Alhamdulillah, semua buku dan perlengkapan tulis telah tiba di sekolah dasar di desa kami. Anak-anak sangat senang. Kami akan mulai mengadakan sesi membaca bersama dengan guru-guru lokal mulai minggu depan.'],
            ],
            'energi-surya-terjangkau-untuk-desa' => [
                ['title' => 'Survey Lapangan Selesai', 'content' => 'Tim teknis kami telah menyelesaikan survei lapangan ke 50 rumah warga yang akan dipasangi panel surya. Data instalasi akan kami proses minggu ini.'],
            ],
        ];

        foreach ($campaigns as $campaign) {
            $templates = $updateTemplates[$campaign->slug] ?? null;

            if ($templates) {
                foreach ($templates as $update) {
                    CampaignUpdate::create([
                        'campaign_id' => $campaign->id,
                        'title' => $update['title'],
                        'content' => $update['content'],
                    ]);
                }
            } else {
                for ($i = 0; $i < 1; $i++) {
                    CampaignUpdate::factory()->create([
                        'campaign_id' => $campaign->id,
                    ]);
                }
            }
        }

        $total = CampaignUpdate::count();
        $this->command->info("✓ CampaignUpdateSeeder: {$total} update berhasil dibuat");
    }
}
