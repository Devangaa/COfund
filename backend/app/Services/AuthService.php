<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): User
    {
        $data["password"] = Hash::make($data["password"]);
        $data["role"] = "backer";
        $data["balance"] = 0;

        $user = User::create($data);

        event(new Registered($user));

        return $user;
    }

    public function login(array $data): array
    {
        $credentials = [
            "email" => $data["email"],
            "password" => $data["password"],
        ];

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                "email" => ["These credentials do not match our records."],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->is_suspended) {
            Auth::logout();
            throw ValidationException::withMessages([
                "email" => ["This account has been suspended."],
            ])->status(403);
        }

        $token = $user->createToken("auth-token")->plainTextToken;

        return [
            "user" => $user,
            "token" => $token,
        ];
    }

    public function logout($user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function sendPasswordResetLink(string $email): string
    {
        return Password::sendResetLink(["email" => $email]);
    }

    public function resetPassword(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password) {
            $user->password = Hash::make($password);
            $user->save();
            event(new PasswordReset($user));
        });
    }

    public function upgradeToCreator(User $user, array $data): User
    {
        if ($user->role !== 'backer') {
            throw ValidationException::withMessages([
                'role' => ['Only backers can upgrade to creator role.'],
            ])->status(409);
        }

        $user->update([
            'role' => 'creator',
        ]);

        return $user->fresh();
    }
}
