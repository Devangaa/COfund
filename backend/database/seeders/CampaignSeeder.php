<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            'campaign_images',
            'campaign_tiers',
            'campaign_updates',
            'backings',
            'transactions',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        DB::table('campaigns')->truncate();

        $creator1 = User::where('email', 'budi@test.com')->first();
        $creator2 = User::where('email', 'citra@test.com')->first();
        $admin = User::where('email', 'dono@test.com')->first();

        $catTech = Category::where('slug', 'teknologi')->first();
        $catEdu = Category::where('slug', 'pendidikan')->first();
        $catSoc = Category::where('slug', 'sosial-kemanusiaan')->first();

        $campaigns = [
            [
                'id' => 1,
                'user_id' => $creator1->id,
                'category_id' => $catTech->id,
                'title' => 'Bantu Anak Pedalaman Tepukan',
                'slug' => 'bantu-anak-pedalaman-tepukan',
                'description' => 'Proyek pengadaan buku dan perlengkapan sekolah untuk anak-anak di daerah pedalaman Papua. Dana akan digunakan untuk membeli buku, pensil, dan perlengkapan tulis lainnya.',
                'target_amount' => 250000000,
                'collected_amount' => 0,
                'deadline' => now()->addDays(60),
                'status' => 'active',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(5),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            [
                'id' => 2,
                'user_id' => $creator1->id,
                'category_id' => $catEdu->id,
                'title' => 'Beasiswa Digital 2026',
                'slug' => 'beasiswa-digital-2026',
                'description' => 'Program beasiswa bagi 100 siswa berprestasi untuk mengikuti kursus digital marketing dan programming selama 6 bulan.',
                'target_amount' => 500000000,
                'collected_amount' => 0,
                'deadline' => now()->addDays(90),
                'status' => 'success',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(20),
                'created_at' => now()->subDays(90),
                'updated_at' => now()->subDays(90),
            ],
            [
                'id' => 3,
                'user_id' => $creator2->id,
                'category_id' => $catSoc->id,
                'title' => 'Energi Surya Terjangkau untuk Desa',
                'slug' => 'energi-surya-terjangkau-untuk-desa',
                'description' => 'Instalasi panel surya di 50 rumah warga desa untuk mengurangi ketergantungan pada PLN dan biaya listrik.',
                'target_amount' => 300000000,
                'collected_amount' => 0,
                'deadline' => now()->addDays(45),
                'status' => 'review',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'id' => 4,
                'user_id' => $creator1->id,
                'category_id' => $catTech->id,
                'title' => 'KitaBisa Ayo!',
                'slug' => 'kitabisa-ayo',
                'description' => 'Platform edukasi gratis untuk kurang mampu. Aplikasi mobile berbasis Android untuk pembelajaran mandiri.',
                'target_amount' => 750000000,
                'collected_amount' => 0,
                'deadline' => now()->addDays(120),
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'user_id' => $creator1->id,
                'category_id' => $catEdu->id,
                'title' => 'Proyek Pengembangan Sekolah Dasar',
                'slug' => 'proyek-pengembangan-sekolah-dasar',
                'description' => 'Renovasi 3 kelas sekolah dasar di daerah terpencil, termasuk pembuatan ruang baca dan laboratorium sains.',
                'target_amount' => 150000000,
                'collected_amount' => 0,
                'deadline' => now()->subDays(5),
                'status' => 'failed',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(60),
                'created_at' => now()->subDays(60),
                'updated_at' => now()->subDays(10),
            ],
            [
                'id' => 6,
                'user_id' => $creator1->id,
                'category_id' => $catTech->id,
                'title' => 'QA Draft Campaign',
                'slug' => 'qa-draft-campaign',
                'description' => 'Dedicated draft campaign fixture for CRUD testing.',
                'target_amount' => 200000000,
                'collected_amount' => 0,
                'deadline' => now()->addDays(120),
                'status' => 'draft',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ];

        DB::table('campaigns')->insert(array_map(function (array $data): array {
            $timestamp = now();
            return array_merge([
                'video_url' => null,
                'rejection_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $data);
        }, $campaigns));

        $this->command->info('CampaignSeeder: 6 deterministic campaigns created (including the QA draft fixture)');
    }
}
