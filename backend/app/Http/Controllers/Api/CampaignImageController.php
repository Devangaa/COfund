<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteCampaignImageRequest;
use App\Http\Requests\StoreCampaignImageRequest;
use App\Http\Resources\CampaignImageResource;
use App\Models\Campaign;
use App\Services\CampaignImageService;
use Illuminate\Http\JsonResponse;

class CampaignImageController extends Controller
{
    public function __construct(
        protected CampaignImageService $campaignImageService
    ) {
    }

    public function store(StoreCampaignImageRequest $request, Campaign $campaign): JsonResponse
    {
        $image = $this->campaignImageService->create(
            $campaign,
            $request->file('image')
        );

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => new CampaignImageResource($image),
        ], 201);
    }

    public function destroyMany(DeleteCampaignImageRequest $request, Campaign $campaign): JsonResponse
    {
        $this->campaignImageService->deleteMany($campaign, $request->validated('ids'));

        return response()->json([
            'success' => true,
            'message' => 'Selected images deleted successfully',
        ]);
    }
}
