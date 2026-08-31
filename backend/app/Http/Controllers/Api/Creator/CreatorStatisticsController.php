<?php

namespace App\Http\Controllers\Api\Creator;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexStatisticsRequest;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;

class CreatorStatisticsController extends Controller
{
    public function __construct(
        protected StatisticsService $statisticsService
    ) {
    }

    public function index(IndexStatisticsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $period = $validated['period'] ?? 'daily';
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $stats = $this->statisticsService->getCreatorStatistics($request->user(), $period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
