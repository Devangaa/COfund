<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        "name",
        "email",
        "password",
        "role",
        "balance",
        "is_suspended",
        "suspended_at",
    ];

    protected $hidden = [
        "password",
        "remember_token",
    ];

    protected $casts = [
        "email_verified_at" => "datetime",
        "balance" => "decimal:2",
        "is_suspended" => "boolean",
        "suspended_at" => "datetime",
    ];

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, "user_id");
    }

    public function backings()
    {
        return $this->hasMany(Backing::class, "user_id");
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, "user_id");
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, "user_id");
    }
}
