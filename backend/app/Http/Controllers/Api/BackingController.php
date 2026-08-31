<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBackingRequest;
use App\Http\Resources\BackingResource;
use App\Models\Backing;
use App\Models\Campaign;
use App\Models\User;
use App\Services\BackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackingController extends Controller
{
    public function __construct(
        protected BackingService $backingService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 50);

        $query = Backing::with(['campaign.creator', 'tier']);

        if ($user->role === User::ROLE_ADMIN) {
            $backings = $query->latest()->paginate($perPage)->appends($request->query());
        } elseif ($user->role === User::ROLE_CREATOR) {
            $backings = $query
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($perPage)->appends($request->query());
        } else {
            $backings = $query
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($perPage)->appends($request->query());
        }

        return response()->json([
            'success' => true,
            'data' => BackingResource::collection($backings),
            'meta' => [
                'pagination' => [
                    'current_page' => $backings->currentPage(),
                    'last_page' => $backings->lastPage(),
                    'per_page' => $backings->perPage(),
                    'total' => $backings->total(),
                ],
            ],
        ]);
    }

    public function store(StoreBackingRequest $request, Campaign $campaign): JsonResponse
    {
        $backing = $this->backingService->create(
            $request->validated(),
            $campaign,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Backing created successfully',
            'data' => new BackingResource($backing),
        ], 201);
    }

    public function indexByCampaign(Request $request, Campaign $campaign): JsonResponse
    {
        $user = $request->user();

        if ($user->role === User::ROLE_ADMIN) {
            // Admin can view all backings for any campaign
        } elseif ($user->role === User::ROLE_CREATOR) {
            if ($campaign->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view backings for your own campaigns.',
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin and creator roles can view campaign backings.',
            ], 403);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 50);

        $backings = Backing::where('campaign_id', $campaign->id)
            ->with(['backer', 'tier'])
            ->latest()
            ->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => BackingResource::collection($backings),
            'meta' => [
                'pagination' => [
                    'current_page' => $backings->currentPage(),
                    'last_page' => $backings->lastPage(),
                    'per_page' => $backings->perPage(),
                    'total' => $backings->total(),
                ],
            ],
        ]);
    }
}
