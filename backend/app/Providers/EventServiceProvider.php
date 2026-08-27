<?php

namespace App\Providers;

use App\Events\BackingCreated;
use App\Events\CampaignApproved;
use App\Events\CampaignFunded;
use App\Events\CampaignRejected;
use App\Events\DepositProcessed;
use App\Events\WithdrawalProcessed;
use App\Listeners\HandleBackingCreated;
use App\Listeners\HandleCampaignApproved;
use App\Listeners\HandleCampaignFunded;
use App\Listeners\HandleCampaignRejected;
use App\Listeners\HandleWalletTransaction;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        CampaignFunded::class => [
            HandleCampaignFunded::class,
        ],
        CampaignApproved::class => [
            HandleCampaignApproved::class,
        ],
        CampaignRejected::class => [
            HandleCampaignRejected::class,
        ],
        BackingCreated::class => [
            HandleBackingCreated::class,
        ],
        DepositProcessed::class => [
            [HandleWalletTransaction::class, 'handleDeposit'],
        ],
        WithdrawalProcessed::class => [
            [HandleWalletTransaction::class, 'handleWithdrawal'],
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
