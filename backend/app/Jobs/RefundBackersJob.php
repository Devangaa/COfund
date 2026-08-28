<?php

namespace App\Jobs;

use App\Mail\RefundProcessed;
use App\Models\Campaign;
use App\Models\Notification;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class RefundBackersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Campaign $campaign
    ) {
    }

    public function handle(TransactionService $transactionService): void
    {
        $transactionService->refundBackers($this->campaign);

        $backerIds = $this->campaign->backings()
            ->where('status', 'refunded')
            ->distinct()
            ->pluck('user_id');

        foreach ($backerIds as $userId) {
            $user = \App\Models\User::find($userId);
            $backing = $this->campaign->backings()->where('user_id', $userId)->first();

            Notification::create([
                'user_id' => $userId,
                'type' => 'refund',
                'title' => "Dana dikembalikan: {$this->campaign->title}",
                'body' => "Kampanye \"{$this->campaign->title}\" gagal. Dana sebesar {$backing->amount} telah dikembalikan ke saldo Anda.",
                'data' => json_encode([
                    'campaign_id' => $this->campaign->id,
                    'campaign_slug' => $this->campaign->slug,
                    'refund_amount' => $backing->amount,
                ]),
            ]);

            if ($user && $user->email_verified_at) {
                Mail::to($user->email)->queue(new RefundProcessed($user, $this->campaign, $backing->amount));
            }
        }
    }
}
