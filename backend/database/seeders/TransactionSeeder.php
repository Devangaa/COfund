<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $backer = User::where('email', 'adi@test.com')->first();

        $transactions = [
            [
                'user_id' => $backer->id,
                'backing_id' => null,
                'campaign_id' => null,
                'type' => 'deposit',
                'amount' => 5000000,
                'status' => 'success',
                'reference' => 'mock_deposit_seed_1',
            ],
            [
                'user_id' => $backer->id,
                'backing_id' => null,
                'campaign_id' => null,
                'type' => 'withdrawal',
                'amount' => 500000,
                'status' => 'success',
                'reference' => 'mock_withdrawal_seed_1',
            ],
        ];

        foreach ($transactions as $tx) {
            Transaction::create($tx);
        }

        $count = Transaction::whereNull('backing_id')->count();
        $this->command->info("✓ TransactionSeeder: {$count} transaksi non-backing (deposit/withdrawal) berhasil dibuat");
    }
}
