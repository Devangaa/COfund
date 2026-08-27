<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepositRequest;
use App\Http\Requests\StoreWithdrawRequest;
use App\Http\Resources\TransactionResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    public function deposit(StoreDepositRequest $request): JsonResponse
    {
        $transaction = $this->walletService->deposit(
            $request->user(),
            (float) $request->input('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Deposit successful',
            'data' => new TransactionResource($transaction),
        ], 201);
    }

    public function withdraw(StoreWithdrawRequest $request): JsonResponse
    {
        $transaction = $this->walletService->withdraw(
            $request->user(),
            (float) $request->input('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal successful',
            'data' => new TransactionResource($transaction),
        ], 201);
    }
}
