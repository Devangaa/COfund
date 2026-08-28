<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            CampaignSeeder::class,
            CampaignImageSeeder::class,
            CampaignTierSeeder::class,
            CampaignUpdateSeeder::class,
            BackingSeeder::class,
            TransactionSeeder::class,
        ]);

        Schema::enableForeignKeyConstraints();
    }
}
