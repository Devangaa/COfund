<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteTierRequest;
use App\Http\Requests\StoreTierRequest;
use App\Http\Requests\UpdateTierRequest;
use App\Http\Resources\CampaignTierResource;
use App\Models\Campaign;
use App\Models\CampaignTier;
use App\Services\TierService;
use Illuminate\Http\JsonResponse;

class TierController extends Controller
{
    public function __construct(
        protected TierService $tierService
    ) {
    }

    public function store(StoreTierRequest $request, Campaign $campaign): JsonResponse
    {
        $tier = $this->tierService->create($campaign, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tier created successfully',
            'data' => new CampaignTierResource($tier),
        ], 201);
    }

    public function update(UpdateTierRequest $request, Campaign $campaign, CampaignTier $tier): JsonResponse
    {
        $tier = $this->tierService->update($campaign, $tier, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tier updated successfully',
            'data' => new CampaignTierResource($tier),
        ]);
    }

    public function destroyMany(DeleteTierRequest $request, Campaign $campaign): JsonResponse
    {
        $this->tierService->deleteMany($campaign, $request->validated('ids'));

        return response()->json([
            'success' => true,
            'message' => 'Selected tiers deleted successfully',
        ]);
    }
}
