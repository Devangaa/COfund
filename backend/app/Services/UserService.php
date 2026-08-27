<?php

namespace App\Services;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Backing;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function suspend(User $user, User $admin): void
    {
        if ($user->id === $admin->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot suspend yourself',
            ]);
        }

        if ($user->is_suspended) {
            return;
        }

        $user->update([
            'is_suspended' => true,
            'suspended_at' => now(),
        ]);

        event(new \App\Events\UserSuspended($user));
    }

    public function unsuspend(User $user): void
    {
        if (!$user->is_suspended) {
            return;
        }

        $user->update([
            'is_suspended' => false,
            'suspended_at' => null,
        ]);

        event(new \App\Events\UserUnsuspended($user));
    }

    public function getUserStats(User $user): array
    {
        $backingCount = $user->backings()->count();
        $campaignCount = $user->role === 'creator'
            ? $user->campaigns()->count()
            : 0;

        return [
            'total_backings' => $backingCount,
            'total_campaigns' => $campaignCount,
            'total_spent' => (float) $user->backings()->sum('amount'),
            'total_contributed' => (float) $user->backings()->sum('amount'),
        ];
    }
}
