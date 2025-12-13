<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Models\DailyMetric;
use AIArmada\Chip\Models\Purchase;
use Illuminate\Support\Carbon;

/**
 * Local analytics service - computes metrics from local data.
 */
class LocalAnalyticsService
{
    /**
     * Get comprehensive dashboard metrics from LOCAL data.
     */
    public function getDashboardMetrics(Carbon $startDate, Carbon $endDate): DashboardMetrics
    {
        return new DashboardMetrics(
            revenue: $this->getRevenueMetrics($startDate, $endDate),
            transactions: $this->getTransactionMetrics($startDate, $endDate),
            paymentMethods: $this->getPaymentMethodBreakdown($startDate, $endDate),
            failures: $this->getFailureAnalysis($startDate, $endDate),
        );
    }

    /**
     * Revenue metrics from local purchases.
     */
    public function getRevenueMetrics(Carbon $startDate, Carbon $endDate): RevenueMetrics
    {
        $metrics = Purchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue,
                SUM(CASE WHEN status = "refunded" THEN refund_amount_minor ELSE 0 END) as refunds,
                COUNT(CASE WHEN status = "paid" THEN 1 END) as paid_count,
                AVG(CASE WHEN status = "paid" THEN total_minor END) as avg_transaction
            ')
            ->first();

        // Get previous period for comparison
        $periodLength = $startDate->diffInDays($endDate);
        $previousStart = $startDate->copy()->subDays($periodLength + 1);
        $previousEnd = $startDate->copy()->subDay();

        $previous = Purchase::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('status', 'paid')
            ->sum('total_minor');

        $currentRevenue = $metrics->revenue ?? 0;
        $growthRate = $previous > 0
            ? (($currentRevenue - $previous) / $previous) * 100
            : ($currentRevenue > 0 ? 100 : 0);

        return new RevenueMetrics(
            grossRevenue: (int) $currentRevenue,
            refunds: (int) ($metrics->refunds ?? 0),
            netRevenue: (int) $currentRevenue - (int) ($metrics->refunds ?? 0),
            transactionCount: (int) ($metrics->paid_count ?? 0),
            averageTransaction: (float) ($metrics->avg_transaction ?? 0),
            growthRate: round($growthRate, 2),
        );
    }

    /**
     * Transaction metrics from local data.
     */
    public function getTransactionMetrics(Carbon $startDate, Carbon $endDate): TransactionMetrics
    {
        $metrics = Purchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status IN ("failed", "error") THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN ("pending", "created") THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) as refunded
            ')
            ->first();

        $total = $metrics->total ?? 0;
        $successful = $metrics->successful ?? 0;
        $successRate = $total > 0 ? round($successful / $total * 100, 2) : 0;

        return new TransactionMetrics(
            total: (int) $total,
            successful: (int) $successful,
            failed: (int) ($metrics->failed ?? 0),
            pending: (int) ($metrics->pending ?? 0),
            refunded: (int) ($metrics->refunded ?? 0),
            successRate: $successRate,
        );
    }

    /**
     * Payment method breakdown from local data.
     *
     * @return array<int, array{method: string, attempts: int, successful: int, success_rate: float, revenue: int}>
     */
    public function getPaymentMethodBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        return Purchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COALESCE(payment_method, "unknown") as payment_method,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue
            ')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method,
                'attempts' => (int) $row->total_attempts,
                'successful' => (int) $row->successful,
                'success_rate' => $row->total_attempts > 0
                    ? round($row->successful / $row->total_attempts * 100, 2)
                    : 0,
                'revenue' => (int) $row->revenue,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->toArray();
    }

    /**
     * Failure analysis from local data.
     *
     * @return array<int, array{reason: string, count: int, lost_revenue: int}>
     */
    public function getFailureAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        return Purchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['failed', 'error'])
            ->selectRaw('
                COALESCE(failure_reason, "Unknown") as failure_reason,
                COUNT(*) as count,
                SUM(total_minor) as lost_revenue
            ')
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'reason' => $row->failure_reason,
                'count' => (int) $row->count,
                'lost_revenue' => (int) ($row->lost_revenue ?? 0),
            ])
            ->toArray();
    }

    /**
     * Revenue trend for charts.
     *
     * @return array<int, array{period: string, count: int, revenue: int}>
     */
    public function getRevenueTrend(Carbon $startDate, Carbon $endDate, string $groupBy = 'day'): array
    {
        $interval = match ($groupBy) {
            'hour' => 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00")',
            'day' => 'DATE(created_at)',
            'week' => 'YEARWEEK(created_at, 1)',
            'month' => 'DATE_FORMAT(created_at, "%Y-%m-01")',
            default => 'DATE(created_at)',
        };

        return Purchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->selectRaw("{$interval} as period, 
                COUNT(*) as count,
                SUM(total_minor) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'count' => (int) $row->count,
                'revenue' => (int) $row->revenue,
            ])
            ->toArray();
    }

    /**
     * Get metrics from pre-aggregated daily metrics table.
     *
     * @return array<int, DailyMetric>
     */
    public function getAggregatedMetrics(Carbon $startDate, Carbon $endDate, ?string $paymentMethod = null): array
    {
        $query = DailyMetric::query()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($paymentMethod !== null) {
            $query->where('payment_method', $paymentMethod);
        } else {
            $query->whereNull('payment_method'); // Get totals only
        }

        return $query->orderBy('date')->get()->all();
    }
}
