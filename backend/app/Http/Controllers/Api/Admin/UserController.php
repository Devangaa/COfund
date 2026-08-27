<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['nullable', 'string', 'in:backer,creator,admin'],
            'is_suspended' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('is_suspended')) {
            $query->where('is_suspended', (bool) $request->query('is_suspended'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->query('per_page', 15), 50);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['campaigns', 'backings']);

        $backingCount = $user->backings()->count();
        $campaignCount = $user->role === 'creator'
            ? $user->campaigns()->count()
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($user),
                'stats' => [
                    'total_backings' => $backingCount,
                    'total_campaigns_created' => $campaignCount,
                    'total_amount_backed' => (float) $user->backings()->sum('amount'),
                ],
            ],
        ]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->userService->suspend($user, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User suspended successfully',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function unsuspend(User $user): JsonResponse
    {
        $this->userService->unsuspend($user);

        return response()->json([
            'success' => true,
            'message' => 'User unsuspended successfully',
            'data' => new UserResource($user->fresh()),
        ]);
    }
}
