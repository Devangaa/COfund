<?php

namespace App\Listeners;

use App\Models\Notification;
use App\Events\UserUnsuspended;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserUnsuspended as UserUnsuspendedMail;

class HandleUserUnsuspended
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
    public function handle(UserUnsuspended $event): void
    {
        $user = $event->user;

        // Create in-app notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'account_unsuspended',
            'title' => 'Akun Anda Telah Diaktifkan Kembali',
            'body' => "Akun Anda ({$user->email}) telah diaktifkan kembali oleh administrator.",
            'data' => json_encode([
                'user_id' => $user->id,
                'action' => 'unsuspend',
            ]),
        ]);

        // Send email to user if verified
        if ($user->email_verified_at && $user->email) {
            Mail::to($user->email)->queue(new UserUnsuspendedMail($user));
        }
    }
}
