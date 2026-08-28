<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Notification;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class DisburseCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Campaign $campaign
    ) {
    }

    public function handle(TransactionService $transactionService): void
    {
        $transactionService->disburseCampaign($this->campaign);

        $creator = $this->campaign->creator;
        $collected = (float) $this->campaign->collected_amount;
        $platformFee = $collected * config('cofund.platform_fee', 0.05);
        $disbursement = $collected - $platformFee;

        Notification::create([
            'user_id' => $creator->id,
            'type' => 'disbursement',
            'title' => "Dana berhasil dicairkan: {$this->campaign->title}",
            'body' => "Kampanye Anda \"{$this->campaign->title}\" berhasil dicairkan. {$disbursement} telah ditambahkan ke saldo Anda. Biaya platform: {$platformFee}.",
            'data' => json_encode([
                'campaign_id' => $this->campaign->id,
                'campaign_slug' => $this->campaign->slug,
                'disbursement_amount' => $disbursement,
                'platform_fee' => $platformFee,
            ]),
        ]);

        if ($creator->email_verified_at) {
            Mail::to($creator->email)->queue(new \App\Mail\DisbursementProcessed($creator, $this->campaign, $disbursement, $platformFee));
        }
    }
}
