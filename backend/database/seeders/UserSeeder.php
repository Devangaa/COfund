<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Zaki Backer',
            'email' => 'zaki@test.com',
            'password' => Hash::make('password'),
            'role' => 'backer',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Zaki Creator 1',
            'email' => 'zaki2@test.com',
            'password' => Hash::make('password'),
            'role' => 'creator',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Zaki Creator 2',
            'email' => 'zaki3@test.com',
            'password' => Hash::make('password'),
            'role' => 'creator',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Zaki Admin',
            'email' => 'zaki4@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }
}
