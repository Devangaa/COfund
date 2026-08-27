<?php

namespace App\Events;

use App\Models\Backing;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Campaign $campaign,
        public User $backer,
        public Backing $backing
    ) {
    }
}
