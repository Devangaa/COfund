<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post("/register", [AuthController::class, "register"]);
Route::post("/login", [AuthController::class, "login"]);
Route::post("/forgot-password", [AuthController::class, "forgotPassword"]);
Route::post("/reset-password", [AuthController::class, "resetPassword"]);

Route::middleware("auth:sanctum")->group(function () {
    Route::post("/logout", [AuthController::class, "logout"]);
    Route::get("/me", [AuthController::class, "me"]);
    Route::post("/email/resend", function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                "success" => false,
                "message" => "Email already verified",
            ], 400);
        }
        $request->user()->sendEmailVerificationNotification();
        return response()->json([
            "success" => true,
            "message" => "Verification email sent",
        ]);
    })->name("verification.resend");
});

Route::get("/email/verify/{id}/{hash}", function (Request $request) {
    $request->validate(["id" => "exists:users,id"]);

    if (! $request->hasValidSignature()) {
        return response()->json([
            "success" => false,
            "message" => "Invalid signature",
        ], 403);
    }

    $user = User::find($request->route("id"));

    if (! $user) {
        return response()->json([
            "success" => false,
            "message" => "User not found",
        ], 404);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json([
            "success" => true,
            "message" => "Email already verified",
        ]);
    }

    $user->markEmailAsVerified();
    event(new Verified($user));

    return response()->json([
        "success" => true,
        "message" => "Email verified successfully",
    ]);
})->middleware(["signed", "throttle:60,1"])->name("verification.verify");
