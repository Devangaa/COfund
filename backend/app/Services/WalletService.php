<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\DepositProcessed;
use App\Events\WithdrawalProcessed;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function deposit(User $user, float $amount, ?string $note = null): Transaction
    {
        $this->ensureActive($user);

        return DB::transaction(function () use ($user, $amount, $note) {
            $user->deposit($amount);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::DEPOSIT,
                'amount' => $amount,
                'status' => TransactionStatus::SUCCESS,
                'reference' => 'deposit_' . now()->timestamp . '_' . $user->id,
            ]);

            DB::afterCommit(fn () => event(new DepositProcessed($user, $transaction)));

            return $transaction;
        });
    }

    public function withdraw(User $user, float $amount, ?string $note = null): Transaction
    {
        $this->ensureActive($user);

        if ($user->balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient balance',
            ]);
        }

        return DB::transaction(function () use ($user, $amount, $note) {
            $user->withdraw($amount);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::WITHDRAWAL,
                'amount' => $amount,
                'status' => TransactionStatus::SUCCESS,
                'reference' => 'withdrawal_' . now()->timestamp . '_' . $user->id,
            ]);

            DB::afterCommit(fn () => event(new WithdrawalProcessed($user, $transaction)));

            return $transaction;
        });
    }

    protected function ensureActive(User $user): void
    {
        if ($user->is_suspended) {
            throw ValidationException::withMessages([
                'user' => 'Account is suspended',
            ]);
        }
    }
}
