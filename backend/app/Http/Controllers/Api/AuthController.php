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
        $userResource = new UserResource($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $userResource,
            ],
            // Backwards compatibility helpers
            'user' => $userResource,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());
        $userResource = new UserResource($result['user']);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $userResource,
                'token' => $result['token'],
            ],
            // Backwards compatibility helpers
            'user' => $userResource,
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $userResource = new UserResource($request->user());

        return response()->json([
            'success' => true,
            'data' => $userResource,
            // Backwards compatibility helper
            'user' => $userResource,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->email);

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->validated());

        if ($status === PasswordBrokerContract::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
            'errors' => [
                'email' => [__($status)],
            ],
        ], 422);
    }

    public function upgradeToCreator(UpgradeToCreatorRequest $request): JsonResponse
    {
        $user = $this->authService->upgradeToCreator($request->user(), $request->validated());
        $userResource = new UserResource($user);

        return response()->json([
            'success' => true,
            'message' => 'Upgraded to creator successfully',
            'data' => [
                'user' => $userResource,
            ],
            // Backwards compatibility helper
            'user' => $userResource,
        ]);
    }
}
