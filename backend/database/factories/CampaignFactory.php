<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $titles = [
            'Bantu Anak Pedalaman Tepukan',
            'Beasiswa Digital 2026',
            'Energi Surya Terjangkau untuk Desa',
            'KitaBisa Ayo!',
            'Proyek Pengembangan Sekolah Dasar',
        ];

        $title = $this->faker->randomElement($titles);

        return [
            'user_id' => User::factory()->create(['role' => 'creator', 'email_verified_at' => now()]),
            'category_id' => Category::inRandomOrder()->first()->id,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . now()->format('ymd-His'),
            'description' => $this->faker->paragraph(3),
            'target_amount' => $this->faker->randomElement([50000000, 100000000, 250000000, 500000000, 1000000000]),
            'collected_amount' => 0,
            'deadline' => now()->addDays(rand(14, 90)),
            'status' => $this->faker->randomElement(['draft', 'review', 'active', 'success', 'failed']),
            'video_url' => $this->faker->url(),
        ];
    }
}
