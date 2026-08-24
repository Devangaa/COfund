<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteCampaignRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\SubmitCampaignReviewRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'status' => ['nullable', 'string', 'in:draft,review,active,success,failed'],
            'sort' => ['nullable', 'string', 'in:latest,oldest,popular'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Campaign::query()
            ->with(['creator', 'category'])
            ->withCount('updates')
            ->where('status', 'active');

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->query('category'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $sort = $request->query('sort', 'latest');
        if ($sort === 'latest' || $sort === 'oldest') {
            $query->orderBy('created_at', $sort === 'latest' ? 'desc' : 'asc');
        } elseif ($sort === 'popular') {
            $query->orderBy('collected_amount', 'desc');
        }

        $campaigns = $query->paginate(12);

        return response()->json([
            'success' => true,
            'data' => CampaignResource::collection($campaigns),
            'meta' => [
                'pagination' => [
                    'current_page' => $campaigns->currentPage(),
                    'last_page' => $campaigns->lastPage(),
                    'per_page' => $campaigns->perPage(),
                    'total' => $campaigns->total(),
                ],
            ],
        ]);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $campaign->load(['creator', 'category', 'images', 'tiers', 'updates']);

        return response()->json([
            'success' => true,
            'data' => new CampaignResource($campaign),
        ]);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $images = $validated['images'];
        $tiers = $validated['tiers'];
        $data = Arr::except($validated, ['images', 'tiers']);

        $campaign = $this->campaignService->create(
            $request->user(),
            $data,
            $images,
            $tiers
        );

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'data' => new CampaignResource($campaign),
        ], 201);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->update($campaign, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'data' => new CampaignResource($campaign),
        ]);
    }

    public function submitReview(SubmitCampaignReviewRequest $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->submitForReview($campaign);

        return response()->json([
            'success' => true,
            'message' => 'Campaign submitted for review',
            'data' => new CampaignResource($campaign),
        ]);
    }

    public function destroy(DeleteCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $this->campaignService->destroy($campaign);

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully',
        ]);
    }
}
