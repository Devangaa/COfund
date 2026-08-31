<?php

namespace App\Listeners;

use App\Events\CampaignFunded;
use App\Jobs\DisburseCampaignJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleCampaignFunded
{
    /**
     * Handle the event.
     */
    public function handle(CampaignFunded $event): void
    {
        DisburseCampaignJob::dispatch($event->campaign);
    }
}
