<?php

use App\Http\Controllers\Api\Admin\StatisticsController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackingController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignImageController;
use App\Http\Controllers\Api\CampaignUpdateController;
use App\Http\Controllers\Api\TierController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post("/register", [AuthController::class, "register"])->name("register")->middleware("throttle:register");
Route::post("/login", [AuthController::class, "login"])->name("login")->middleware("throttle:login");
Route::post("/forgot-password", [AuthController::class, "forgotPassword"])->name("password.request")->middleware("throttle:password.request");
Route::post("/reset-password", [AuthController::class, "resetPassword"])->name("password.update")->middleware("throttle:password.request");

Route::get("/campaigns", [CampaignController::class, "index"])->name("campaign.index");
Route::get("/campaigns/{campaign:slug}", [CampaignController::class, "show"])->name("campaign.show");

Route::middleware("auth:sanctum")->group(function () {
    Route::post("/logout", [AuthController::class, "logout"])->name("logout");
    Route::get("/me", [AuthController::class, "me"])->name("me");
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

Route::middleware(['auth:sanctum', 'role:creator', 'verified'])->group(function () {
    Route::post("/campaigns", [CampaignController::class, "store"])->name("campaign.store");
    Route::put("/campaigns/{campaign:slug}", [CampaignController::class, "update"])->name("campaign.update");
    Route::delete("/campaigns/{campaign:slug}", [CampaignController::class, "destroy"])->name("campaign.destroy");
    Route::post("/campaigns/{campaign:slug}/submit-review", [CampaignController::class, "submitReview"])->name("campaign.submit-review");

    Route::post("/campaigns/{campaign:slug}/tiers", [TierController::class, "store"])->name("tier.store");
    Route::put("/campaigns/{campaign:slug}/tiers/{tier}", [TierController::class, "update"])->name("tier.update");
    Route::delete("/campaigns/{campaign:slug}/tiers", [TierController::class, "destroyMany"])->name("tier.destroy-many");

    Route::post("/campaigns/{campaign:slug}/images", [CampaignImageController::class, "store"])->name("image.store");
    Route::delete("/campaigns/{campaign:slug}/images", [CampaignImageController::class, "destroyMany"])->name("image.destroy-many");

    Route::post("/campaigns/{campaign:slug}/updates", [CampaignUpdateController::class, "store"])->name("campaign-update.store");
    Route::put("/campaigns/{campaign:slug}/updates/{update}", [CampaignUpdateController::class, "update"])->name("campaign-update.update");
    Route::delete("/campaigns/{campaign:slug}/updates/{update}", [CampaignUpdateController::class, "destroy"])->name("campaign-update.destroy");
});

Route::get("/campaigns/{campaign:slug}/updates", [CampaignUpdateController::class, "index"])->name("campaign-update.index");

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post("/campaigns/{campaign:slug}/back", [BackingController::class, "store"])->name("backings.store");
    Route::get("/backings", [BackingController::class, "index"])->name("backings.index");
    Route::get("/campaigns/{campaign:slug}/backings", [BackingController::class, "indexByCampaign"])->name("campaign.backings.index");

    Route::get("/transactions", [TransactionController::class, "index"])->name("transactions.index");

    // Wallet
    Route::post("/wallet/deposit", [WalletController::class, "deposit"])->name("wallet.deposit");
    Route::post("/wallet/withdraw", [WalletController::class, "withdraw"])->name("wallet.withdraw");
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::put("/admin/campaigns/{campaign:slug}/approve", [CampaignController::class, "approve"])->name("campaign.approve");
    Route::put("/admin/campaigns/{campaign:slug}/reject", [CampaignController::class, "reject"])->name("campaign.reject");
    Route::put("/admin/campaigns/{campaign:slug}/force-fail", [CampaignController::class, "forceFail"])->name("campaign.force-fail");

    // Admin User Management
    Route::get("/admin/users", [UserController::class, "index"])->name("admin.users.index");
    Route::get("/admin/users/{user}", [UserController::class, "show"])->name("admin.users.show");
    Route::put("/admin/users/{user}/suspend", [UserController::class, "suspend"])->name("admin.users.suspend");
    Route::put("/admin/users/{user}/unsuspend", [UserController::class, "unsuspend"])->name("admin.users.unsuspend");

    // Admin Platform Statistics
    Route::get("/admin/statistics", [StatisticsController::class, "index"])->name("admin.statistics");
});

Route::get("/email/verify/notice", function () {
    return response()->json([
        "success" => false,
        "message" => "Email verification required.",
    ], 403);
})->name("verification.notice");

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
