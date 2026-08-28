<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignUpdateFactory extends Factory
{
    protected $model = CampaignUpdate::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'title' => $this->faker->sentence(4, true),
            'content' => $this->faker->paragraph(3),
        ];
    }
}
