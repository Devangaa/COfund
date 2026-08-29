<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpgradeToCreatorRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Auth\Passwords\PasswordBroker as PasswordBrokerContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            "success" => true,
            "user" => new UserResource($user),
            "message" => "Registration successful",
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            "success" => true,
            "user" => new UserResource($result["user"]),
            "token" => $result["token"],
            "message" => "Login successful",
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            "success" => true,
            "message" => "Logged out successfully",
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            "success" => true,
            "user" => new UserResource($request->user()),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->email);

        return response()->json([
            "success" => true,
            "message" => "If the email exists, a password reset link has been sent.",
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->validated());

        if ($status === PasswordBrokerContract::PASSWORD_RESET) {
            return response()->json([
                "success" => true,
                "message" => "Password reset successfully",
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => __($status),
            "errors" => [
                "email" => [__($status)],
            ],
        ], 422);
    }

    public function upgradeToCreator(UpgradeToCreatorRequest $request): JsonResponse
    {
        $user = $this->authService->upgradeToCreator($request->user(), $request->validated());

        return response()->json([
            "success" => true,
            "user" => new UserResource($user),
            "message" => "Upgraded to creator successfully",
        ]);
    }
}
