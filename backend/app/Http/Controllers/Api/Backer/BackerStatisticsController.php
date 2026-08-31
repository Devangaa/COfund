<?php

namespace App\Http\Controllers\Api\Backer;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackerStatisticsController extends Controller
{
    public function __construct(
        protected StatisticsService $statisticsService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $stats = $this->statisticsService->getBackerStatistics($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
