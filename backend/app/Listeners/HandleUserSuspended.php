<?php

namespace App\Listeners;

use App\Models\Notification;
use App\Events\UserSuspended;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserSuspended as UserSuspendedMail;

class HandleUserSuspended
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserSuspended $event): void
    {
        $user = $event->user;

        // Create in-app notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'account_suspended',
            'title' => 'Akun Anda Telah Disuspend',
            'body' => "Akun Anda ({$user->email}) telah disuspend oleh administrator karena pelanggaran kebijakan.",
            'data' => json_encode([
                'user_id' => $user->id,
                'action' => 'suspend',
            ]),
        ]);

        // Send email to user if verified
        if ($user->email_verified_at && $user->email) {
            Mail::to($user->email)->queue(new UserSuspendedMail($user));
        }
    }
}
