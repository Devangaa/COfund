<?php

namespace App\Http\Controllers\Api\Creator;

use App\Http\Controllers\Controller;
use App\Models\Backing;
use App\Models\Campaign;
use App\Enums\CampaignStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorStatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $creator = $request->user();

        $request->validate([
            'period' => ['nullable', 'string', 'in:daily,weekly,monthly,yearly'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $period = $request->query('period', 'daily');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $platformFee = config('cofund.platform_fee', 0.1);

        $campaigns = $creator->campaigns()
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', $endDate));

        $totalCampaigns = (clone $campaigns)->count();
        $totalCollected = (clone $campaigns)->sum('collected_amount');
        $totalTarget = (clone $campaigns)->sum('target_amount');
        $totalFees = (clone $campaigns)
            ->join('backings', 'campaigns.id', '=', 'backings.campaign_id')
            ->sum('backings.amount') * $platformFee;

        $statusDistribution = (clone $campaigns)
            ->select('status', \DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalBackings = Backing::whereIn('campaign_id', function ($q) use ($creator) {
            $q->select('id')->from('campaigns')->where('user_id', $creator->id);
        })->count();

        $chartData = $this->getCreatorChartData($period, $startDate, $endDate, $creator->id);

        return response()->json([
            'success' => true,
            'data' => [
                'total_campaigns' => $totalCampaigns,
                'total_backings' => $totalBackings,
                'total_collected' => (float) $totalCollected,
                'total_target' => (float) $totalTarget,
                'platform_fee' => (float) $totalFees,
                'platform_fee_rate' => $platformFee,
                'completion_rate' => $totalTarget > 0
                    ? round(($totalCollected / $totalTarget) * 100, 2)
                    : 0,
                'status_distribution' => $this->normalizeStatusDistribution($statusDistribution),
                'chart' => $chartData,
            ],
        ]);
    }

    protected function getCreatorChartData(string $period, ?string $startDate, ?string $endDate, int $creatorId): array
    {
        $groupBy = match ($period) {
            'daily' => 'DATE(created_at)',
            'weekly' => "DATE_FORMAT(created_at, '%Y-W%u')",
            'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
            'yearly' => "YEAR(created_at)",
            default => 'DATE(created_at)',
        };

        $query = Campaign::selectRaw("{$groupBy} as period, COUNT(*) as campaigns, SUM(collected_amount) as collected")
            ->where('user_id', $creatorId)
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', $endDate))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $query->map(function ($row) {
            return [
                'period' => $row->period,
                'campaigns' => (int) $row->campaigns,
                'collected' => (float) $row->collected,
            ];
        })->toArray();
    }

    protected function normalizeStatusDistribution(array $raw): array
    {
        foreach (CampaignStatus::cases() as $status) {
            if (!isset($raw[$status->value])) {
                $raw[$status->value] = 0;
            }
        }
        return $raw;
    }
}
