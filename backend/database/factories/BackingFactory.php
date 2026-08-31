<?php

namespace Database\Factories;

use App\Models\Backing;
use App\Models\Campaign;
use App\Models\CampaignTier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackingFactory extends Factory
{
    protected $model = Backing::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => Campaign::factory(),
            'tier_id' => null,
            'amount' => $this->faker->randomElement([10000, 25000, 50000, 75000, 100000, 250000, 500000]),
            'status' => 'completed',
        ];
    }
}
