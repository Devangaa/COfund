<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignImageFactory extends Factory
{
    protected $model = CampaignImage::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'url' => 'https://picsum.photos/seed/' . $this->faker->word() . '/600/400',
            'is_primary' => false,
        ];
    }
}
