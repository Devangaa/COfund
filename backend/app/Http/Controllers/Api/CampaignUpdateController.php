<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteCampaignUpdateRequest;
use App\Http\Requests\StoreCampaignUpdateRequest;
use App\Http\Requests\UpdateCampaignUpdateRequest;
use App\Http\Resources\CampaignUpdateResource;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Services\CampaignUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignUpdateController extends Controller
{
    public function __construct(
        protected CampaignUpdateService $campaignUpdateService
    ) {
    }

    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 50);

        $updates = $campaign->updates()->latest()->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => CampaignUpdateResource::collection($updates),
            'meta' => [
                'pagination' => [
                    'current_page' => $updates->currentPage(),
                    'last_page' => $updates->lastPage(),
                    'per_page' => $updates->perPage(),
                    'total' => $updates->total(),
                ],
            ],
        ]);
    }

    public function store(StoreCampaignUpdateRequest $request, Campaign $campaign): JsonResponse
    {
        $update = $this->campaignUpdateService->create($campaign, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Update posted successfully',
            'data' => new CampaignUpdateResource($update),
        ], 201);
    }

    public function update(UpdateCampaignUpdateRequest $request, Campaign $campaign, CampaignUpdate $update): JsonResponse
    {
        $update = $this->campaignUpdateService->update($update, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Update updated successfully',
            'data' => new CampaignUpdateResource($update),
        ]);
    }

    public function destroy(DeleteCampaignUpdateRequest $request, Campaign $campaign, CampaignUpdate $update): JsonResponse
    {
        $this->campaignUpdateService->destroy($update);

        return response()->json([
            'success' => true,
            'message' => 'Update deleted successfully',
        ]);
    }
}
