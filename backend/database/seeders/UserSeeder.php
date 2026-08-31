<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('password_reset_tokens')->truncate();
        DB::table('notifications')->truncate();
        DB::table('personal_access_tokens')->truncate();

        $users = [
            [
                'id' => 1,
                'name' => 'Adi Backer',
                'email' => 'adi@test.com',
                'password' => Hash::make('password'),
                'role' => 'backer',
                'balance' => 5000000,
                'is_suspended' => false,
                'suspended_at' => null,
                'email_verified_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Budi Creator',
                'email' => 'budi@test.com',
                'password' => Hash::make('password'),
                'role' => 'creator',
                'balance' => 0,
                'is_suspended' => false,
                'suspended_at' => null,
                'email_verified_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Citra Creator',
                'email' => 'citra@test.com',
                'password' => Hash::make('password'),
                'role' => 'creator',
                'balance' => 0,
                'is_suspended' => false,
                'suspended_at' => null,
                'email_verified_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Dono Admin',
                'email' => 'dono@test.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'balance' => 0,
                'is_suspended' => false,
                'suspended_at' => null,
                'email_verified_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'Sari Suspended',
                'email' => 'sari.suspended@test.com',
                'password' => Hash::make('password'),
                'role' => 'backer',
                'balance' => 0,
                'is_suspended' => true,
                'suspended_at' => now()->subDay(),
                'email_verified_at' => now(),
            ],
            [
                'id' => 6,
                'name' => 'Unverified Test User',
                'email' => 'unverified@test.com',
                'password' => Hash::make('password'),
                'role' => 'backer',
                'balance' => 0,
                'is_suspended' => false,
                'suspended_at' => null,
                'email_verified_at' => null,
            ],
        ];

        DB::table('users')->truncate();
        DB::table('users')->insert(array_map(function (array $user): array {
            $timestamp = now();
            return array_merge([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $user);
        }, $users));

        $this->command->info('UserSeeder: 6 deterministic users created');
    }
}
