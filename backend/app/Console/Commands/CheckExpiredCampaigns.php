<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Jobs\DisburseCampaignJob;
use App\Jobs\RefundBackersJob;
use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiredCampaigns extends Command
{
    protected $signature = 'campaign:check-expired';
    protected $description = 'Check and process expired campaigns (success or fail + refund backers)';

    public function handle(): void
    {
        $now = Carbon::now();

        $expiredCampaigns = Campaign::where('status', CampaignStatus::ACTIVE)
            ->whereDate('deadline', '<', $now)
            ->get();

        foreach ($expiredCampaigns as $campaign) {
            $target = (float) $campaign->target_amount;
            $collected = (float) $campaign->collected_amount;

            if ($collected >= $target) {
                $campaign->update(['status' => CampaignStatus::SUCCESS]);
                DisburseCampaignJob::dispatch($campaign);
                $this->info("Campaign {$campaign->slug} marked as success and disbursement job dispatched.");
            } else {
                $campaign->update(['status' => CampaignStatus::FAILED]);
                RefundBackersJob::dispatch($campaign);
                $this->info("Campaign {$campaign->slug} marked as failed and refund job dispatched.");
            }
        }

        $this->info("Processed {$expiredCampaigns->count()} expired campaigns.");
    }
}
