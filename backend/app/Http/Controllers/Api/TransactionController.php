<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $request->validate([
            'type' => ['nullable', 'string', 'in:payment,refund,disbursement,platform_fee,deposit,withdrawal'],
            'status' => ['nullable', 'string', 'in:pending,success,failed'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'user_id' => $user->role === 'admin' ? ['nullable', 'integer', 'exists:users,id'] : ['prohibited'],
            'sort' => ['nullable', 'string', 'in:latest,oldest'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Transaction::query();

        if ($user->role === 'admin') {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->query('end_date'));
        }

        $sort = $request->query('sort', 'latest');
        $query->orderBy('created_at', $sort === 'latest' ? 'desc' : 'asc');

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 50);
        $transactions = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($transactions),
            'meta' => [
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }
}
