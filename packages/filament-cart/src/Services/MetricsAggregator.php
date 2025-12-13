<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartDailyMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates raw cart data into daily metrics.
 */
class MetricsAggregator
{
    /**
     * Aggregate metrics for a specific date.
     */
    public function aggregateForDate(Carbon $date, ?string $segment = null): CartDailyMetrics
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $metrics = $this->calculateMetricsForPeriod($startOfDay, $endOfDay, $segment);

        return CartDailyMetrics::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'segment' => $segment,
            ],
            $metrics,
        );
    }

    /**
     * Aggregate totals across a date range.
     *
     * @return array<string, mixed>
     */
    public function aggregateTotals(Carbon $from, Carbon $to): array
    {
        return CartDailyMetrics::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('segment')
            ->selectRaw('
                SUM(carts_created) as total_carts_created,
                SUM(carts_with_items) as total_carts_with_items,
                SUM(checkouts_started) as total_checkouts_started,
                SUM(checkouts_completed) as total_checkouts_completed,
                SUM(checkouts_abandoned) as total_checkouts_abandoned,
                SUM(carts_recovered) as total_carts_recovered,
                SUM(recovered_revenue_cents) as total_recovered_revenue,
                SUM(total_cart_value_cents) as total_cart_value,
                AVG(average_cart_value_cents) as avg_cart_value,
                SUM(fraud_alerts_high) as total_fraud_high,
                SUM(fraud_alerts_medium) as total_fraud_medium
            ')
            ->first()
            ?->toArray() ?? [];
    }

    /**
     * Backfill metrics for a date range.
     */
    public function backfill(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $current = $from->copy();

        while ($current->lte($to)) {
            $this->aggregateForDate($current);
            $current->addDay();
            $count++;
        }

        return $count;
    }

    /**
     * Calculate metrics for a time period.
     *
     * @return array<string, mixed>
     */
    private function calculateMetricsForPeriod(Carbon $start, Carbon $end, ?string $segment = null): array
    {
        $baseQuery = Cart::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($segment !== null) {
            $baseQuery->where(DB::raw("JSON_EXTRACT(metadata, '$.segment')"), $segment);
        }

        // Cart counts
        $cartsCreated = (clone $baseQuery)->count();

        $cartsWithItems = (clone $baseQuery)
            ->whereNotNull('items')
            ->where('items', '!=', '[]')
            ->where('items', '!=', 'null')
            ->count();

        $cartsEmpty = $cartsCreated - $cartsWithItems;

        // Active carts (updated in period with items)
        $cartsActive = Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->whereNotNull('items')
            ->where('items', '!=', '[]')
            ->count();

        // Checkout funnel
        $checkoutsStarted = Cart::query()
            ->whereBetween('checkout_started_at', [$start, $end])
            ->count();

        $checkoutsCompleted = Cart::query()
            ->whereBetween('checkout_completed_at', [$start, $end])
            ->count();

        $checkoutsAbandoned = Cart::query()
            ->whereBetween('checkout_abandoned_at', [$start, $end])
            ->count();

        // Recovery metrics
        $cartsRecovered = Cart::query()
            ->whereBetween('recovered_at', [$start, $end])
            ->count();

        $recoveredRevenue = (int) Cart::query()
            ->whereBetween('recovered_at', [$start, $end])
            ->sum('subtotal');

        // Value metrics
        $totalCartValue = (int) Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->whereNotNull('items')
            ->where('items', '!=', '[]')
            ->sum('subtotal');

        $avgCartValue = $cartsWithItems > 0 ? (int) ($totalCartValue / $cartsWithItems) : 0;

        $totalItems = (int) Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->sum('items_count');

        $avgItemsPerCart = $cartsWithItems > 0 ? $totalItems / $cartsWithItems : 0;

        // Fraud metrics
        $fraudHigh = Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->where('fraud_risk_level', 'high')
            ->count();

        $fraudMedium = Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->where('fraud_risk_level', 'medium')
            ->count();

        $cartsBlocked = Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->whereRaw("JSON_EXTRACT(metadata, '$.blocked') = true")
            ->count();

        // Collaborative metrics
        $collaborativeCarts = Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->where('is_collaborative', true)
            ->count();

        $totalCollaborators = (int) Cart::query()
            ->whereBetween('updated_at', [$start, $end])
            ->where('is_collaborative', true)
            ->sum('collaborator_count');

        return [
            'carts_created' => $cartsCreated,
            'carts_active' => $cartsActive,
            'carts_empty' => $cartsEmpty,
            'carts_with_items' => $cartsWithItems,
            'checkouts_started' => $checkoutsStarted,
            'checkouts_completed' => $checkoutsCompleted,
            'checkouts_abandoned' => $checkoutsAbandoned,
            'recovery_emails_sent' => 0, // Would need to track this separately
            'carts_recovered' => $cartsRecovered,
            'recovered_revenue_cents' => $recoveredRevenue,
            'total_cart_value_cents' => $totalCartValue,
            'average_cart_value_cents' => $avgCartValue,
            'total_items' => $totalItems,
            'average_items_per_cart' => round($avgItemsPerCart, 2),
            'fraud_alerts_high' => $fraudHigh,
            'fraud_alerts_medium' => $fraudMedium,
            'carts_blocked' => $cartsBlocked,
            'collaborative_carts' => $collaborativeCarts,
            'total_collaborators' => $totalCollaborators,
        ];
    }
}
