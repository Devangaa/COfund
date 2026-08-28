<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignTierFactory extends Factory
{
    protected $model = CampaignTier::class;

    public function definition(): array
    {
        $quota = $this->faker->randomElement([0, 10, 20, 50, 100]);

        return [
            'campaign_id' => Campaign::factory(),
            'name' => $this->faker->randomElement([
                'Early Bird', 'Supporter', 'Gold Backer', 'Platinum', 'Custom',
            ]),
            'min_amount' => $this->faker->randomElement([10000, 25000, 50000, 100000, 250000, 500000]),
            'quota' => $quota,
            'remaining_quota' => $quota,
            'reward_description' => $this->faker->sentence(8),
        ];
    }
}
