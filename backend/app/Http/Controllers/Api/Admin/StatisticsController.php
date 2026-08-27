<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backing;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\CampaignStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:daily,weekly,monthly,yearly'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $period = $request->query('period', 'daily');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $dateFilter = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }
        };

        $platformFee = config('cofund.platform_fee', 0.1);

        $totalUsers = User::when($startDate || $endDate, function ($q) use ($dateFilter) {
            $dateFilter($q);
        })->count();

        $totalCampaigns = Campaign::when($startDate || $endDate, function ($q) use ($dateFilter) {
            $dateFilter($q);
        })->count();

        $totalBackings = Backing::when($startDate || $endDate, function ($q) use ($dateFilter) {
            $dateFilter($q);
        })->count();

        $totalCollected = Campaign::when($startDate || $endDate, function ($q) use ($dateFilter) {
            $dateFilter($q);
        })->sum('collected_amount');

        $totalTarget = Campaign::when($startDate || $endDate, function ($q) use ($dateFilter) {
            $dateFilter($q);
        })->sum('target_amount');

        $totalFee = Backing::when($startDate || $endDate, function ($q) use ($dateFilter) {
            $dateFilter($q);
        })->sum('amount') * $platformFee;

        $statusDistribution = Campaign::select('status', \DB::raw('count(*) as total'))
            ->when($startDate || $endDate, function ($q) use ($dateFilter) {
                $dateFilter($q);
            })
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $topCampaigns = Campaign::withCount('backings')
            ->withSum('backings', 'amount')
            ->orderByDesc('collected_amount')
            ->limit(5)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'slug' => $c->slug,
                    'collected_amount' => (float) $c->collected_amount,
                    'target_amount' => (float) $c->target_amount,
                    'backings_count' => $c->backings_count,
                    'status' => $c->status,
                ];
            });

        $dailyData = $this->getDailyStats($period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_campaigns' => $totalCampaigns,
                'total_backings' => $totalBackings,
                'total_collected' => (float) $totalCollected,
                'total_target' => (float) $totalTarget,
                'platform_fee' => (float) $totalFee,
                'platform_fee_rate' => $platformFee,
                'completion_rate' => $totalCampaigns > 0
                    ? round(($totalCollected / max($totalTarget, 1)) * 100, 2)
                    : 0,
                'status_distribution' => $this->normalizeStatusDistribution($statusDistribution),
                'top_campaigns' => $topCampaigns,
                'chart' => $dailyData,
            ],
        ]);
    }

    protected function getDailyStats(string $period, ?string $startDate, ?string $endDate): array
    {
        $groupBy = match ($period) {
            'daily' => 'DATE(created_at)',
            'weekly' => "DATE_FORMAT(created_at, '%Y-W%u')",
            'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
            'yearly' => "YEAR(created_at)",
            default => 'DATE(created_at)',
        };

        $query = Campaign::selectRaw("{$groupBy} as period, COUNT(*) as campaigns, SUM(collected_amount) as collected")
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
