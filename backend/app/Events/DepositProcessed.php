<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepositProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Transaction $transaction
    ) {
    }
}
