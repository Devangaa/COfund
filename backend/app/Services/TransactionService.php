<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Campaign;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Disburse collected funds to creator and deduct platform fee.
     * Disbursement amount is 95% of collected_amount (5% platform fee).
     */
    public function disburseCampaign(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $collectedAmount = (float) $campaign->collected_amount;
            $platformFee = $collectedAmount * 0.05;
            $disbursementAmount = $collectedAmount - $platformFee;

            $creator = $campaign->creator;

            $creator->deposit($disbursementAmount);

            Transaction::create([
                'user_id' => $creator->id,
                'campaign_id' => $campaign->id,
                'type' => TransactionType::DISBURSEMENT,
                'amount' => $disbursementAmount,
                'status' => TransactionStatus::SUCCESS,
                'reference' => 'disbursement_' . $campaign->id . '_' . now()->timestamp,
            ]);

            Transaction::create([
                'user_id' => $creator->id,
                'campaign_id' => $campaign->id,
                'type' => TransactionType::PLATFORM_FEE,
                'amount' => $platformFee,
                'status' => TransactionStatus::SUCCESS,
                'reference' => 'platform_fee_' . $campaign->id . '_' . now()->timestamp,
            ]);
        });
    }

    /**
     * Refund all backers for a failed campaign.
     */
    public function refundBackers(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $backings = $campaign->backings()
                ->where('status', '!=', 'refunded')
                ->get();

            foreach ($backings as $backing) {
                $backer = $backing->backer;
                $backer->deposit((float) $backing->amount);

                Transaction::create([
                    'user_id' => $backer->id,
                    'backing_id' => $backing->id,
                    'campaign_id' => $campaign->id,
                    'type' => TransactionType::REFUND,
                    'amount' => (float) $backing->amount,
                    'status' => TransactionStatus::SUCCESS,
                    'reference' => 'refund_' . $backing->id . '_' . now()->timestamp,
                ]);

                $backing->update(['status' => 'refunded']);
            }
        });
    }
}
