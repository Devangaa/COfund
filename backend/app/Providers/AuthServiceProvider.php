<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return config("app.frontend_url", config("app.url"))
                . "/reset-password?token=" . $token
                . "&email=" . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
