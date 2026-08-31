<?php

namespace App\Services;

use App\Enums\BackingStatus;
use App\Enums\CampaignStatus;
use App\Models\Backing;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * Get platform statistics for Admin Dashboard.
     *
     * @param string $period
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getAdminStatistics(string $period = 'daily', ?string $startDate = null, ?string $endDate = null): array
    {
        $dateFilter = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }
        };

        $platformFee = (float) config('cofund.platform_fee', 0.1);

        $totalUsers = User::when($startDate || $endDate, fn ($q) => $dateFilter($q))->count();
        $totalCampaigns = Campaign::when($startDate || $endDate, fn ($q) => $dateFilter($q))->count();
        $totalBackings = Backing::when($startDate || $endDate, fn ($q) => $dateFilter($q))->count();
        $totalCollected = (float) Campaign::when($startDate || $endDate, fn ($q) => $dateFilter($q))->sum('collected_amount');
        $totalTarget = (float) Campaign::when($startDate || $endDate, fn ($q) => $dateFilter($q))->sum('target_amount');
        $totalFee = (float) (Backing::when($startDate || $endDate, fn ($q) => $dateFilter($q))->sum('amount') * $platformFee);

        $statusDistribution = Campaign::select('status', DB::raw('count(*) as total'))
            ->when($startDate || $endDate, fn ($q) => $dateFilter($q))
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

        $chartData = $this->getAdminChartData($period, $startDate, $endDate);

        return [
            'total_users' => $totalUsers,
            'total_campaigns' => $totalCampaigns,
            'total_backings' => $totalBackings,
            'total_collected' => $totalCollected,
            'total_target' => $totalTarget,
            'platform_fee' => $totalFee,
            'platform_fee_rate' => $platformFee,
            'completion_rate' => $totalCampaigns > 0
                ? round(($totalCollected / max($totalTarget, 1)) * 100, 2)
                : 0,
            'status_distribution' => $this->normalizeStatusDistribution($statusDistribution),
            'top_campaigns' => $topCampaigns,
            'chart' => $chartData,
        ];
    }

    /**
     * Get campaign statistics for a Creator.
     *
     * @param User $creator
     * @param string $period
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getCreatorStatistics(User $creator, string $period = 'daily', ?string $startDate = null, ?string $endDate = null): array
    {
        $platformFee = (float) config('cofund.platform_fee', 0.1);

        $campaignsQuery = $creator->campaigns()
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', $endDate));

        $totalCampaigns = (clone $campaignsQuery)->count();
        $totalCollected = (float) (clone $campaignsQuery)->sum('collected_amount');
        $totalTarget = (float) (clone $campaignsQuery)->sum('target_amount');
        $totalFees = (float) ((clone $campaignsQuery)
            ->join('backings', 'campaigns.id', '=', 'backings.campaign_id')
            ->sum('backings.amount') * $platformFee);

        $statusDistribution = (clone $campaignsQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalBackings = Backing::whereIn('campaign_id', function ($q) use ($creator) {
            $q->select('id')->from('campaigns')->where('user_id', $creator->id);
        })->count();

        $chartData = $this->getCreatorChartData($period, $startDate, $endDate, $creator->id);

        return [
            'total_campaigns' => $totalCampaigns,
            'total_backings' => $totalBackings,
            'total_collected' => $totalCollected,
            'total_target' => $totalTarget,
            'platform_fee' => $totalFees,
            'platform_fee_rate' => $platformFee,
            'completion_rate' => $totalTarget > 0
                ? round(($totalCollected / $totalTarget) * 100, 2)
                : 0,
            'status_distribution' => $this->normalizeStatusDistribution($statusDistribution),
            'chart' => $chartData,
        ];
    }

    /**
     * Get statistics for a Backer.
     *
     * @param User $backer
     * @return array
     */
    public function getBackerStatistics(User $backer): array
    {
        $backings = $backer->backings()->with('campaign');

        $totalBacked = (float) $backings->where('status', BackingStatus::COMPLETED)->sum('amount');
        $totalRefunded = (float) $backings->where('status', BackingStatus::REFUNDED)->sum('amount');
        $totalBackings = $backings->count();
        $totalCampaignsBacked = $backings->distinct('campaign_id')->count('campaign_id');

        return [
            'total_backed' => $totalBacked,
            'total_refunded' => $totalRefunded,
            'total_backings' => $totalBackings,
            'total_campaigns_backed' => $totalCampaignsBacked,
        ];
    }

    protected function getAdminChartData(string $period, ?string $startDate, ?string $endDate): array
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
