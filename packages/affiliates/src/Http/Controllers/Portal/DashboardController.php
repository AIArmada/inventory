<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Services\NetworkService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly NetworkService $networkService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');

        $stats = $this->getOverviewStats($affiliate);
        $recentConversions = $this->getRecentConversions($affiliate);
        $chartData = $this->getChartData($affiliate);

        return response()->json([
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'code' => $affiliate->code,
                'status' => $affiliate->status->value,
                'rank' => $affiliate->rank?->name,
            ],
            'stats' => $stats,
            'recent_conversions' => $recentConversions,
            'chart_data' => $chartData,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate');
        $period = $request->get('period', 'month');

        $startDate = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            'all' => null,
            default => now()->subMonth(),
        };

        $stats = $this->getDetailedStats($affiliate, $startDate);

        return response()->json($stats);
    }

    private function getOverviewStats(Affiliate $affiliate): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth();

        $thisMonthConversions = $affiliate->conversions()
            ->where('occurred_at', '>=', $startOfMonth)
            ->count();

        $thisMonthRevenue = $affiliate->conversions()
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('total_minor');

        $thisMonthCommission = $affiliate->conversions()
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('commission_minor');

        $lastMonthCommission = $affiliate->conversions()
            ->whereBetween('occurred_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
            ->sum('commission_minor');

        $pendingCommission = $affiliate->conversions()
            ->whereIn('status', ['pending', 'qualified'])
            ->sum('commission_minor');

        $paidCommission = $affiliate->conversions()
            ->where('status', 'paid')
            ->sum('commission_minor');

        $balance = $affiliate->balance;

        return [
            'this_month' => [
                'conversions' => $thisMonthConversions,
                'revenue_minor' => $thisMonthRevenue,
                'commission_minor' => $thisMonthCommission,
            ],
            'pending_commission_minor' => $pendingCommission,
            'paid_commission_minor' => $paidCommission,
            'available_balance_minor' => $balance?->available_minor ?? 0,
            'holding_balance_minor' => $balance?->holding_minor ?? 0,
            'commission_change_percent' => $lastMonthCommission > 0
                ? round(($thisMonthCommission - $lastMonthCommission) / $lastMonthCommission * 100, 1)
                : 0,
            'network_size' => $affiliate->children()->count(),
        ];
    }

    private function getRecentConversions(Affiliate $affiliate, int $limit = 10): array
    {
        return $affiliate->conversions()
            ->with(['attribution'])
            ->latest('occurred_at')
            ->take($limit)
            ->get()
            ->map(fn (AffiliateConversion $conversion) => [
                'id' => $conversion->id,
                'order_id' => $conversion->order_id,
                'total_minor' => $conversion->total_minor,
                'commission_minor' => $conversion->commission_minor,
                'currency' => $conversion->currency,
                'status' => $conversion->status->value,
                'occurred_at' => $conversion->occurred_at?->toIso8601String(),
            ])
            ->all();
    }

    private function getChartData(Affiliate $affiliate, int $days = 30): array
    {
        $stats = AffiliateDailyStat::where('affiliate_id', $affiliate->id)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        return $stats->map(fn (AffiliateDailyStat $stat) => [
            'date' => $stat->date->format('Y-m-d'),
            'clicks' => $stat->clicks,
            'conversions' => $stat->conversions,
            'revenue_minor' => $stat->revenue_minor,
            'commission_minor' => $stat->commission_minor,
        ])->all();
    }

    private function getDetailedStats(Affiliate $affiliate, ?CarbonInterface $startDate): array
    {
        $query = $affiliate->conversions();

        if ($startDate) {
            $query->where('occurred_at', '>=', $startDate);
        }

        $conversions = $query->get();

        return [
            'total_conversions' => $conversions->count(),
            'total_revenue_minor' => $conversions->sum('total_minor'),
            'total_commission_minor' => $conversions->sum('commission_minor'),
            'average_order_minor' => $conversions->count() > 0
                ? (int) round($conversions->avg('total_minor'))
                : 0,
            'average_commission_minor' => $conversions->count() > 0
                ? (int) round($conversions->avg('commission_minor'))
                : 0,
            'by_status' => $conversions->groupBy('status')->map->count()->all(),
        ];
    }
}
