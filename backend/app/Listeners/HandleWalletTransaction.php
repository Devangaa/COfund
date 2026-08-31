<?php

namespace App\Listeners;

use App\Events\DepositProcessed;
use App\Events\WithdrawalProcessed;
use App\Models\Notification;

class HandleWalletTransaction
{
    public function handleDeposit(DepositProcessed $event): void
    {
        $user = $event->user;
        $transaction = $event->transaction;

        Notification::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'title' => 'Deposit berhasil diproses',
            'body' => 'Deposit sebesar Rp ' . number_format($transaction->amount, 0, ',', '.') . ' telah ditambahkan ke dompet Anda.',
            'data' => json_encode([
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'reference' => $transaction->reference,
            ]),
        ]);
    }

    public function handleWithdrawal(WithdrawalProcessed $event): void
    {
        $user = $event->user;
        $transaction = $event->transaction;

        Notification::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'title' => 'Withdrawal berhasil diproses',
            'body' => 'Penarikan sebesar Rp ' . number_format($transaction->amount, 0, ',', '.') . ' telah diproses.',
            'data' => json_encode([
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'reference' => $transaction->reference,
            ]),
        ]);
    }
}
