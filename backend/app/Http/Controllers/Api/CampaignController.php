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
use Illuminate\Support\Facades\Auth;

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
            'scope' => ['nullable', 'string', 'in:mine,public'],
            'sort' => ['nullable', 'string', 'in:latest,oldest,popular'],
            'search' => ['nullable', 'string', 'max:255'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);        $user = Auth::guard('sanctum')->user();
        $query = Campaign::query()
            ->with(['creator', 'category'])
            ->withCount('updates');

        if ($user && $user->role === 'creator') {
            // Creator: default menampilkan semua campaign publik (active)
            // Gunakan ?scope=mine untuk melihat semua campaign milik sendiri (semua status)
            if ($request->query('scope') === 'mine') {
                $query->where('user_id', $user->id);
                // filter status hanya berlaku untuk campaign milik sendiri
                if ($request->filled('status')) {
                    $query->where('status', $request->query('status'));
                }
            } else {
                $query->where('status', 'active');
            }
        } elseif ($user && $user->role === 'admin') {
            // admin: semua campaign (no scope)
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
        } else {
            // guest / backer: hanya campaign publik (active) — status filter diabaikan
            $query->where('status', 'active');
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->query('category'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhereHas('creator', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('min_amount')) {
            $query->where('target_amount', '>=', $request->query('min_amount'));
        }

        if ($request->filled('max_amount')) {
            $query->where('collected_amount', '<=', $request->query('max_amount'));
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->query('end_date'));
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

    public function approve(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->approve($campaign, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Campaign approved and is now active',
            'data' => new CampaignResource($campaign),
        ]);
    }

    public function reject(Request $request, Campaign $campaign): JsonResponse
    {
        $note = $request->input('rejection_note', '');
        $campaign = $this->campaignService->reject($campaign, $request->user(), $note);

        return response()->json([
            'success' => true,
            'message' => 'Campaign rejected',
            'data' => new CampaignResource($campaign),
        ]);
    }

    public function forceFail(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->campaignService->forceFail($campaign);

        if ($campaign->backings()->where('status', '!=', 'refunded')->exists()) {
            \App\Jobs\RefundBackersJob::dispatch($campaign);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign force-failed',
            'data' => new CampaignResource($campaign),
        ]);
    }
}
