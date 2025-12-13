<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Models\DailyMetric;
use AIArmada\Chip\Models\Purchase;
use Illuminate\Support\Carbon;

/**
 * Aggregates purchase data into daily metrics.
 */
class MetricsAggregator
{
    /**
     * Aggregate metrics for a specific date.
     */
    public function aggregateForDate(Carbon $date): void
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Aggregate by payment method
        $byMethod = Purchase::query()
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('
                payment_method,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful_count,
                SUM(CASE WHEN status IN ("failed", "error") THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) as refunded_count,
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue_minor,
                SUM(CASE WHEN status = "refunded" THEN refund_amount_minor ELSE 0 END) as refunds_minor,
                AVG(CASE WHEN status = "paid" THEN total_minor END) as avg_transaction
            ')
            ->groupBy('payment_method')
            ->get();

        foreach ($byMethod as $row) {
            $successRate = $row->total_attempts > 0
                ? $row->successful_count / $row->total_attempts * 100
                : 0;

            DailyMetric::updateOrCreate(
                [
                    'date' => $date->toDateString(),
                    'payment_method' => $row->payment_method,
                ],
                [
                    'total_attempts' => $row->total_attempts,
                    'successful_count' => $row->successful_count,
                    'failed_count' => $row->failed_count,
                    'refunded_count' => $row->refunded_count,
                    'revenue_minor' => $row->revenue_minor ?? 0,
                    'refunds_minor' => $row->refunds_minor ?? 0,
                    'success_rate' => round($successRate, 2),
                    'avg_transaction_minor' => $row->avg_transaction ?? 0,
                    'failure_breakdown' => $this->getFailureBreakdown($startOfDay, $endOfDay, $row->payment_method),
                ]
            );
        }

        // Aggregate totals (null payment_method)
        $this->aggregateTotals($date);
    }

    /**
     * Aggregate total metrics for the date (across all payment methods).
     */
    public function aggregateTotals(Carbon $date): void
    {
        $totals = DailyMetric::query()
            ->where('date', $date->toDateString())
            ->whereNotNull('payment_method')
            ->selectRaw('
                SUM(total_attempts) as total_attempts,
                SUM(successful_count) as successful_count,
                SUM(failed_count) as failed_count,
                SUM(refunded_count) as refunded_count,
                SUM(revenue_minor) as revenue_minor,
                SUM(refunds_minor) as refunds_minor
            ')
            ->first();

        $successRate = ($totals->total_attempts ?? 0) > 0
            ? ($totals->successful_count ?? 0) / $totals->total_attempts * 100
            : 0;

        $avgTransaction = ($totals->successful_count ?? 0) > 0
            ? ($totals->revenue_minor ?? 0) / $totals->successful_count
            : 0;

        DailyMetric::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'payment_method' => null,
            ],
            [
                'total_attempts' => $totals->total_attempts ?? 0,
                'successful_count' => $totals->successful_count ?? 0,
                'failed_count' => $totals->failed_count ?? 0,
                'refunded_count' => $totals->refunded_count ?? 0,
                'revenue_minor' => $totals->revenue_minor ?? 0,
                'refunds_minor' => $totals->refunds_minor ?? 0,
                'success_rate' => round($successRate, 2),
                'avg_transaction_minor' => round($avgTransaction, 2),
            ]
        );
    }

    /**
     * Get failure breakdown for a date range and payment method.
     *
     * @return array<string, int>
     */
    protected function getFailureBreakdown(Carbon $startDate, Carbon $endDate, ?string $paymentMethod): array
    {
        $query = Purchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['failed', 'error']);

        if ($paymentMethod !== null) {
            $query->where('payment_method', $paymentMethod);
        }

        return $query
            ->selectRaw('COALESCE(failure_reason, "Unknown") as reason, COUNT(*) as count')
            ->groupBy('reason')
            ->pluck('count', 'reason')
            ->toArray();
    }

    /**
     * Backfill metrics for a date range.
     */
    public function backfill(Carbon $startDate, Carbon $endDate): int
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $this->aggregateForDate($current);
            $current->addDay();
            $days++;
        }

        return $days;
    }
}
