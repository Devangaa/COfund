<?php

namespace App\Http\Controllers\Api\Backer;

use App\Http\Controllers\Controller;
use App\Models\Backing;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackerStatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $backings = $user->backings()->with('campaign');

        $totalBacked = $backings->where('status', \App\Enums\BackingStatus::COMPLETED)->sum('amount');
        $totalRefunded = $backings->where('status', \App\Enums\BackingStatus::REFUNDED)->sum('amount');
        $totalBackings = $backings->count();
        $totalCampaignsBacked = $backings->distinct('campaign_id')->count('campaign_id');

        return response()->json([
            'success' => true,
            'data' => [
                'total_backed' => (float) $totalBacked,
                'total_refunded' => (float) $totalRefunded,
                'total_backings' => $totalBackings,
                'total_campaigns_backed' => $totalCampaignsBacked,
            ],
        ]);
    }
}
