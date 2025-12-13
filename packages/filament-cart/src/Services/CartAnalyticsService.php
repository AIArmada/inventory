<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Data\AbandonmentAnalysis;
use AIArmada\FilamentCart\Data\ConversionFunnel;
use AIArmada\FilamentCart\Data\DashboardMetrics;
use AIArmada\FilamentCart\Data\RecoveryMetrics;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartDailyMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for retrieving and calculating cart analytics.
 */
class CartAnalyticsService
{
    /**
     * Get dashboard metrics for a date range.
     */
    public function getDashboardMetrics(Carbon $from, Carbon $to): DashboardMetrics
    {
        $current = CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->selectRaw('
                SUM(carts_created) as total_carts,
                SUM(carts_active) as active_carts,
                SUM(checkouts_abandoned) as abandoned_carts,
                SUM(carts_recovered) as recovered_carts,
                SUM(total_cart_value_cents) as total_value,
                SUM(checkouts_started) as checkouts_started,
                SUM(checkouts_completed) as checkouts_completed,
                AVG(average_cart_value_cents) as avg_value
            ')
            ->first();

        // Calculate previous period for comparison
        $periodDays = $from->diffInDays($to) + 1;
        $previousFrom = $from->copy()->subDays($periodDays);
        $previousTo = $from->copy()->subDay();

        $previous = CartDailyMetrics::query()
            ->whereBetween('date', [$previousFrom->toDateString(), $previousTo->toDateString()])
            ->whereNull('segment')
            ->selectRaw('
                SUM(checkouts_started) as checkouts_started,
                SUM(checkouts_completed) as checkouts_completed,
                SUM(checkouts_abandoned) as abandoned_carts,
                SUM(carts_recovered) as recovered_carts
            ')
            ->first();

        // Current rates
        $conversionRate = ($current?->checkouts_started ?? 0) > 0
            ? ($current->checkouts_completed ?? 0) / $current->checkouts_started
            : 0.0;

        $abandonmentRate = ($current?->checkouts_started ?? 0) > 0
            ? ($current->abandoned_carts ?? 0) / $current->checkouts_started
            : 0.0;

        $recoveryRate = ($current?->abandoned_carts ?? 0) > 0
            ? ($current->recovered_carts ?? 0) / $current->abandoned_carts
            : 0.0;

        // Previous rates for comparison
        $prevConversionRate = ($previous?->checkouts_started ?? 0) > 0
            ? ($previous->checkouts_completed ?? 0) / $previous->checkouts_started
            : 0.0;

        $prevAbandonmentRate = ($previous?->checkouts_started ?? 0) > 0
            ? ($previous->abandoned_carts ?? 0) / $previous->checkouts_started
            : 0.0;

        $prevRecoveryRate = ($previous?->abandoned_carts ?? 0) > 0
            ? ($previous->recovered_carts ?? 0) / $previous->abandoned_carts
            : 0.0;

        return new DashboardMetrics(
            total_carts: (int) ($current?->total_carts ?? 0),
            active_carts: (int) ($current?->active_carts ?? 0),
            abandoned_carts: (int) ($current?->abandoned_carts ?? 0),
            recovered_carts: (int) ($current?->recovered_carts ?? 0),
            total_value_cents: (int) ($current?->total_value ?? 0),
            conversion_rate: $conversionRate,
            abandonment_rate: $abandonmentRate,
            recovery_rate: $recoveryRate,
            average_cart_value_cents: (int) ($current?->avg_value ?? 0),
            conversion_rate_change: $prevConversionRate > 0
                ? ($conversionRate - $prevConversionRate) / $prevConversionRate
                : null,
            abandonment_rate_change: $prevAbandonmentRate > 0
                ? ($abandonmentRate - $prevAbandonmentRate) / $prevAbandonmentRate
                : null,
            recovery_rate_change: $prevRecoveryRate > 0
                ? ($recoveryRate - $prevRecoveryRate) / $prevRecoveryRate
                : null,
        );
    }

    /**
     * Get conversion funnel data.
     */
    public function getConversionFunnel(Carbon $from, Carbon $to): ConversionFunnel
    {
        $metrics = CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->selectRaw('
                SUM(carts_created) as carts_created,
                SUM(carts_with_items) as items_added,
                SUM(checkouts_started) as checkout_started,
                SUM(checkouts_completed) as checkout_completed
            ')
            ->first();

        return ConversionFunnel::calculate(
            cartsCreated: (int) ($metrics?->carts_created ?? 0),
            itemsAdded: (int) ($metrics?->items_added ?? 0),
            checkoutStarted: (int) ($metrics?->checkout_started ?? 0),
            checkoutCompleted: (int) ($metrics?->checkout_completed ?? 0),
        );
    }

    /**
     * Get recovery metrics.
     */
    public function getRecoveryMetrics(Carbon $from, Carbon $to): RecoveryMetrics
    {
        $metrics = CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->selectRaw('
                SUM(checkouts_abandoned) as total_abandoned,
                SUM(recovery_emails_sent) as recovery_attempts,
                SUM(carts_recovered) as successful_recoveries,
                SUM(recovered_revenue_cents) as recovered_revenue
            ')
            ->first();

        // Get strategy breakdown from cart metadata
        $strategyBreakdown = Cart::query()
            ->whereBetween('recovered_at', [$from, $to])
            ->whereNotNull('recovered_at')
            ->selectRaw("
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.last_recovery_strategy')) as strategy,
                COUNT(*) as conversions,
                SUM(subtotal) as revenue
            ")
            ->groupBy('strategy')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->strategy ?? 'unknown' => [
                    'attempts' => 0,
                    'conversions' => $row->conversions,
                    'revenue' => $row->revenue,
                ],
            ])
            ->toArray();

        return RecoveryMetrics::calculate(
            totalAbandoned: (int) ($metrics?->total_abandoned ?? 0),
            recoveryAttempts: (int) ($metrics?->recovery_attempts ?? 0),
            successfulRecoveries: (int) ($metrics?->successful_recoveries ?? 0),
            recoveredRevenueCents: (int) ($metrics?->recovered_revenue ?? 0),
            byStrategy: $strategyBreakdown,
        );
    }

    /**
     * Get value trends over time.
     *
     * @return array<int, array{date: string, value: int, count: int}>
     */
    public function getValueTrends(Carbon $from, Carbon $to, string $interval = 'day'): array
    {
        $groupBy = match ($interval) {
            'week' => 'YEARWEEK(date)',
            'month' => "DATE_FORMAT(date, '%Y-%m')",
            default => 'date',
        };

        return CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->selectRaw("
                {$groupBy} as period,
                SUM(total_cart_value_cents) as total_value,
                SUM(carts_with_items) as cart_count
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->period,
                'value' => (int) $row->total_value,
                'count' => (int) $row->cart_count,
            ])
            ->toArray();
    }

    /**
     * Get abandonment analysis.
     */
    public function getAbandonmentAnalysis(Carbon $from, Carbon $to): AbandonmentAnalysis
    {
        // By hour
        $byHour = Cart::query()
            ->whereBetween('checkout_abandoned_at', [$from, $to])
            ->selectRaw('HOUR(checkout_abandoned_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // Fill in missing hours
        for ($i = 0; $i < 24; $i++) {
            $byHour[$i] = $byHour[$i] ?? 0;
        }
        ksort($byHour);

        // By day of week
        $byDayOfWeek = Cart::query()
            ->whereBetween('checkout_abandoned_at', [$from, $to])
            ->selectRaw('DAYOFWEEK(checkout_abandoned_at) - 1 as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day')
            ->toArray();

        // Fill in missing days
        for ($i = 0; $i < 7; $i++) {
            $byDayOfWeek[$i] = $byDayOfWeek[$i] ?? 0;
        }
        ksort($byDayOfWeek);

        // By cart value range
        $byCartValueRange = Cart::query()
            ->whereBetween('checkout_abandoned_at', [$from, $to])
            ->selectRaw("
                CASE
                    WHEN subtotal < 2500 THEN 'Under \$25'
                    WHEN subtotal < 5000 THEN '\$25-\$50'
                    WHEN subtotal < 10000 THEN '\$50-\$100'
                    WHEN subtotal < 25000 THEN '\$100-\$250'
                    ELSE 'Over \$250'
                END as value_range,
                COUNT(*) as count
            ")
            ->groupBy('value_range')
            ->pluck('count', 'value_range')
            ->toArray();

        // By items count
        $byItemsCount = Cart::query()
            ->whereBetween('checkout_abandoned_at', [$from, $to])
            ->selectRaw("
                CASE
                    WHEN items_count = 1 THEN '1 item'
                    WHEN items_count <= 3 THEN '2-3 items'
                    WHEN items_count <= 5 THEN '4-5 items'
                    ELSE '6+ items'
                END as items_range,
                COUNT(*) as count
            ")
            ->groupBy('items_range')
            ->pluck('count', 'items_range')
            ->toArray();

        // Common exit points
        $commonExitPoints = Cart::query()
            ->whereBetween('checkout_abandoned_at', [$from, $to])
            ->selectRaw("
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.last_step')), 'Unknown') as exit_point,
                COUNT(*) as count
            ")
            ->groupBy('exit_point')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'exit_point')
            ->toArray();

        $totalAbandonments = array_sum($byHour);

        return AbandonmentAnalysis::fromData(
            byHour: $byHour,
            byDayOfWeek: $byDayOfWeek,
            byCartValueRange: $byCartValueRange,
            byItemsCount: $byItemsCount,
            commonExitPoints: $commonExitPoints,
            totalAbandonments: $totalAbandonments,
        );
    }

    /**
     * Get segment comparison.
     *
     * @return Collection<int, CartDailyMetrics>
     */
    public function getSegmentComparison(Carbon $from, Carbon $to): Collection
    {
        return CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('segment')
            ->selectRaw('
                segment,
                SUM(carts_created) as total_carts,
                SUM(checkouts_completed) as conversions,
                AVG(average_cart_value_cents) as avg_value,
                SUM(checkouts_abandoned) / NULLIF(SUM(checkouts_started), 0) as abandonment_rate
            ')
            ->groupBy('segment')
            ->orderByDesc('conversions')
            ->get();
    }
}
