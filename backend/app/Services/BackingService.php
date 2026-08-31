<?php

namespace App\Services;

use App\Enums\BackingStatus;
use App\Enums\CampaignStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\BackingCreated;
use App\Events\CampaignFunded;
use App\Models\Backing;
use App\Models\Campaign;
use App\Models\CampaignTier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BackingService
{
    public function create(array $data, Campaign $campaign, User $backer): Backing
    {
        $this->ensureCanBack($campaign, $backer);

        $tier = null;
        if (isset($data['tier_id'])) {
            $tier = CampaignTier::where('campaign_id', $campaign->id)
                ->lockForUpdate()
                ->findOrFail($data['tier_id']);

            $this->ensureTierAvailable($tier, $data['amount']);
        } else {
            $this->ensureMinimumAmount($data['amount']);
        }

        return DB::transaction(function () use ($data, $campaign, $backer, $tier) {
            $backing = Backing::create([
                'user_id' => $backer->id,
                'campaign_id' => $campaign->id,
                'tier_id' => $tier?->id,
                'amount' => $data['amount'],
                'status' => BackingStatus::COMPLETED,
            ]);

            Transaction::create([
                'user_id' => $backer->id,
                'backing_id' => $backing->id,
                'campaign_id' => $campaign->id,
                'type' => TransactionType::PAYMENT,
                'amount' => $data['amount'],
                'status' => TransactionStatus::SUCCESS,
                'reference' => 'mock_payment_' . now()->timestamp,
            ]);

            // Deduct backer wallet balance
            if ($backer->balance >= $data['amount']) {
                $backer->decrement('balance', $data['amount']);
            } elseif ($backer->balance > 0) {
                $backer->update(['balance' => 0]);
            }

            if ($tier && !$tier->isUnlimited()) {
                $tier->decrement('remaining_quota');
            }

            $campaign->lockForUpdate()->increment('collected_amount', $data['amount']);

            $backing = $backing->fresh(['tier', 'campaign.creator']);

            $this->checkCampaignReachedTarget($campaign, $backing);

            event(new BackingCreated($campaign, $backer, $backing));

            return $backing;
        });
    }

    protected function ensureCanBack(Campaign $campaign, User $backer): void
    {
        if ($campaign->user_id === $backer->id) {
            throw new AuthorizationException('Creator cannot back their own campaign');
        }

        if ($campaign->status !== CampaignStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'campaign' => 'Campaign must be active to receive backing'],
            );
        }
    }

    protected function ensureTierAvailable(CampaignTier $tier, float $amount): void
    {
        if (!$tier->hasAvailability()) {
            throw ValidationException::withMessages([
                'tier_id' => 'This tier is full'],
            );
        }

        if ($amount < $tier->min_amount) {
            throw ValidationException::withMessages([
                'amount' => 'Backing amount must be at least tier minimum'],
            );
        }
    }

    protected function ensureMinimumAmount(float $amount): void
    {
        if ($amount < 10000) {
            throw ValidationException::withMessages([
                'amount' => 'Minimum backing amount is 10,000'],
            );
        }
    }

    protected function checkCampaignReachedTarget(Campaign $campaign, Backing $backing): void
    {
        $campaign->refresh();

        $target = (float) $campaign->target_amount;
        $collected = (float) $campaign->collected_amount;

        if ($collected >= $target && $campaign->status === CampaignStatus::ACTIVE) {
            $campaign->update(['status' => CampaignStatus::SUCCESS]);

            DB::afterCommit(fn () => event(new CampaignFunded($campaign)));
        }
    }
}
