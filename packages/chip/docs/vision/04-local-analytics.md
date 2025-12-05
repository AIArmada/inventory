# Local Analytics

> **Document:** 04 of 05  
> **Package:** `aiarmada/chip`  
> **Status:** Vision (API-Constrained)

---

## Overview

Build analytics and insights by **aggregating local purchase data**. Chip API only provides account balance and turnover - all other metrics must be computed from our database.

---

## What Chip API Provides

```php
// Only these analytics are from Chip API
$balance = Chip::getAccountBalance();
$turnover = Chip::getAccountTurnover([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
]);
```

**Everything else must be computed locally from `chip_purchases` table.**

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   LOCAL ANALYTICS                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  chip_purchases table                                        │
│        │                                                     │
│        ▼                                                     │
│  ┌─────────────────┐                                        │
│  │   Aggregator    │ ─── Hourly/Daily rollups               │
│  └────────┬────────┘                                        │
│           │                                                  │
│           ▼                                                  │
│  ┌─────────────────┐                                        │
│  │  Metrics Store  │ ─── chip_daily_metrics                 │
│  └────────┬────────┘                                        │
│           │                                                  │
│           ▼                                                  │
│  ┌─────────────────┐                                        │
│  │   Dashboard     │ ─── Widgets, charts                    │
│  └─────────────────┘                                        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Metrics Model

### ChipDailyMetric

```php
/**
 * Pre-aggregated daily metrics from local data
 * 
 * @property string $id
 * @property Carbon $date
 * @property string|null $payment_method
 * @property int $total_attempts
 * @property int $successful_count
 * @property int $failed_count
 * @property int $refunded_count
 * @property int $revenue_minor
 * @property int $refunds_minor
 * @property float $success_rate
 * @property float $avg_transaction_minor
 * @property array|null $failure_breakdown
 */
class ChipDailyMetric extends Model
{
    use HasUuids;
    
    protected $casts = [
        'date' => 'date',
        'success_rate' => 'float',
        'avg_transaction_minor' => 'float',
        'failure_breakdown' => 'array',
    ];
    
    public function getTable(): string
    {
        return config('chip.database.tables.daily_metrics')
            ?? config('chip.database.table_prefix', 'chip_') . 'daily_metrics';
    }
}
```

---

## Analytics Service

```php
class ChipLocalAnalyticsService
{
    /**
     * Get dashboard metrics from LOCAL data
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
     * Revenue metrics from local purchases
     */
    public function getRevenueMetrics(Carbon $startDate, Carbon $endDate): RevenueMetrics
    {
        $metrics = ChipPurchase::query()
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
        $previousStart = $startDate->copy()->subDays($periodLength);
        $previousEnd = $startDate->copy()->subDay();
        
        $previous = ChipPurchase::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('status', 'paid')
            ->sum('total_minor');
        
        $growthRate = $previous > 0 
            ? (($metrics->revenue - $previous) / $previous) * 100 
            : 0;
        
        return new RevenueMetrics(
            grossRevenue: $metrics->revenue ?? 0,
            refunds: $metrics->refunds ?? 0,
            netRevenue: ($metrics->revenue ?? 0) - ($metrics->refunds ?? 0),
            transactionCount: $metrics->paid_count ?? 0,
            averageTransaction: $metrics->avg_transaction ?? 0,
            growthRate: round($growthRate, 2),
        );
    }
    
    /**
     * Payment method breakdown from local data
     */
    public function getPaymentMethodBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        return ChipPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                payment_method,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue
            ')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method,
                'attempts' => $row->total_attempts,
                'successful' => $row->successful,
                'success_rate' => round($row->successful / max(1, $row->total_attempts) * 100, 2),
                'revenue' => $row->revenue,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->toArray();
    }
    
    /**
     * Failure analysis from local data
     */
    public function getFailureAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        return ChipPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['failed', 'error'])
            ->selectRaw('
                failure_reason,
                COUNT(*) as count,
                SUM(total_minor) as lost_revenue
            ')
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'reason' => $row->failure_reason ?? 'Unknown',
                'count' => $row->count,
                'lost_revenue' => $row->lost_revenue,
            ])
            ->toArray();
    }
    
    /**
     * Revenue trend for charts
     */
    public function getRevenueTrend(Carbon $startDate, Carbon $endDate, string $groupBy = 'day'): array
    {
        $interval = match ($groupBy) {
            'hour' => 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00")',
            'day' => 'DATE(created_at)',
            'week' => 'YEARWEEK(created_at)',
            'month' => 'DATE_FORMAT(created_at, "%Y-%m-01")',
            default => 'DATE(created_at)',
        };
        
        return ChipPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->selectRaw("{$interval} as period, 
                COUNT(*) as count,
                SUM(total_minor) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }
}
```

---

## Metrics Aggregator

```php
class MetricsAggregator
{
    /**
     * Aggregate metrics for a specific date
     */
    public function aggregateForDate(Carbon $date): void
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();
        
        // Aggregate by payment method
        $byMethod = ChipPurchase::query()
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
            ChipDailyMetric::updateOrCreate(
                [
                    'date' => $date,
                    'payment_method' => $row->payment_method,
                ],
                [
                    'total_attempts' => $row->total_attempts,
                    'successful_count' => $row->successful_count,
                    'failed_count' => $row->failed_count,
                    'refunded_count' => $row->refunded_count,
                    'revenue_minor' => $row->revenue_minor ?? 0,
                    'refunds_minor' => $row->refunds_minor ?? 0,
                    'success_rate' => $row->total_attempts > 0 
                        ? $row->successful_count / $row->total_attempts * 100 
                        : 0,
                    'avg_transaction_minor' => $row->avg_transaction ?? 0,
                ]
            );
        }
        
        // Aggregate totals (null payment_method)
        $this->aggregateTotals($date);
    }
    
    private function aggregateTotals(Carbon $date): void
    {
        $totals = ChipDailyMetric::query()
            ->where('date', $date)
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
        
        ChipDailyMetric::updateOrCreate(
            [
                'date' => $date,
                'payment_method' => null,
            ],
            [
                'total_attempts' => $totals->total_attempts ?? 0,
                'successful_count' => $totals->successful_count ?? 0,
                'failed_count' => $totals->failed_count ?? 0,
                'refunded_count' => $totals->refunded_count ?? 0,
                'revenue_minor' => $totals->revenue_minor ?? 0,
                'refunds_minor' => $totals->refunds_minor ?? 0,
                'success_rate' => ($totals->total_attempts ?? 0) > 0 
                    ? ($totals->successful_count ?? 0) / $totals->total_attempts * 100 
                    : 0,
            ]
        );
    }
}
```

---

## Aggregation Command

```php
class AggregateChipMetrics extends Command
{
    protected $signature = 'chip:aggregate-metrics {--date= : Date to aggregate (YYYY-MM-DD)}';
    
    public function handle(MetricsAggregator $aggregator): int
    {
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date'))
            : now()->subDay();
        
        $this->info("Aggregating metrics for {$date->toDateString()}");
        
        $aggregator->aggregateForDate($date);
        
        $this->info('Done.');
        
        return self::SUCCESS;
    }
}
```

---

## Database Schema

```php
// chip_daily_metrics table
Schema::create('chip_daily_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->date('date');
    $table->string('payment_method')->nullable();
    $table->integer('total_attempts')->default(0);
    $table->integer('successful_count')->default(0);
    $table->integer('failed_count')->default(0);
    $table->integer('refunded_count')->default(0);
    $table->bigInteger('revenue_minor')->default(0);
    $table->bigInteger('refunds_minor')->default(0);
    $table->decimal('success_rate', 5, 2)->default(0);
    $table->decimal('avg_transaction_minor', 12, 2)->default(0);
    $table->json('failure_breakdown')->nullable();
    $table->timestamps();
    
    $table->unique(['date', 'payment_method']);
    $table->index('date');
});
```

---

## Scheduled Aggregation

```php
// Aggregate yesterday's metrics
$schedule->command('chip:aggregate-metrics')
    ->dailyAt('01:00')
    ->withoutOverlapping();
```

---

## Important Note

**All analytics are computed from local `chip_purchases` data.**

Chip API does NOT provide:
- Transaction analytics
- Payment method breakdown
- Failure analysis
- Revenue trends
- Customer cohorts

These must all be computed from data we store locally when processing payments and webhooks.

---

## Navigation

**Previous:** [03-enhanced-webhooks.md](03-enhanced-webhooks.md)  
**Next:** [05-implementation-roadmap.md](05-implementation-roadmap.md)
