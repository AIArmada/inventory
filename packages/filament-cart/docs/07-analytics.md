---
title: Analytics
---

# Analytics

The package provides comprehensive cart analytics through a dedicated service, DTOs, and an analytics page with interactive widgets.

## CartAnalyticsService

The core service for retrieving and calculating cart analytics.

```php
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Illuminate\Support\Carbon;

$service = app(CartAnalyticsService::class);

// Define date range
$from = Carbon::now()->subDays(30);
$to = Carbon::now();
```

### Dashboard Metrics

Get high-level metrics with period-over-period comparison:

```php
$metrics = $service->getDashboardMetrics($from, $to);

// Access metrics
$metrics->total_carts;              // Total carts in period
$metrics->active_carts;             // Carts with items
$metrics->abandoned_carts;          // Abandoned checkouts
$metrics->recovered_carts;          // Successfully recovered
$metrics->total_value_cents;        // Total cart value
$metrics->conversion_rate;          // Float (0-1)
$metrics->abandonment_rate;         // Float (0-1)
$metrics->recovery_rate;            // Float (0-1)
$metrics->average_cart_value_cents; // Average value

// Period comparison (vs previous period of same length)
$metrics->conversion_rate_change;   // null or float (-1 to +∞)
$metrics->abandonment_rate_change;  // null or float
$metrics->recovery_rate_change;     // null or float
```

### Conversion Funnel

Analyze the cart-to-checkout funnel:

```php
$funnel = $service->getConversionFunnel($from, $to);

$funnel->carts_created;       // Carts created
$funnel->items_added;         // Carts with items added
$funnel->checkout_started;    // Checkout initiated
$funnel->checkout_completed;  // Checkout completed

// Drop-off rates between stages
$funnel->drop_off_rates['created_to_items'];     // Float
$funnel->drop_off_rates['items_to_checkout'];    // Float
$funnel->drop_off_rates['checkout_to_complete']; // Float

// Overall metrics
$funnel->getOverallDropOffRate(); // Float (0-1)
$funnel->getConversionRate();     // Float (0-1)
```

### Recovery Metrics

Track recovery campaign performance:

```php
$recovery = $service->getRecoveryMetrics($from, $to);

$recovery->total_abandoned;         // Total abandoned carts
$recovery->recovery_attempts;       // Recovery messages sent
$recovery->successful_recoveries;   // Recovered carts
$recovery->recovered_revenue_cents; // Revenue recovered
$recovery->recovery_rate;           // Float (0-1)

// Strategy breakdown
foreach ($recovery->by_strategy as $strategy => $data) {
    echo "{$strategy}: {$data['conversions']} conversions, {$data['revenue']} revenue";
}
```

### Value Trends

Track cart value over time:

```php
// By day (default)
$trends = $service->getValueTrends($from, $to);

// By week
$trends = $service->getValueTrends($from, $to, 'week');

// By month
$trends = $service->getValueTrends($from, $to, 'month');

// Result format
foreach ($trends as $point) {
    $point['date'];  // Period identifier
    $point['value']; // Total value in cents
    $point['count']; // Cart count
}
```

### Abandonment Analysis

Multi-dimensional abandonment breakdown:

```php
$analysis = $service->getAbandonmentAnalysis($from, $to);

$analysis->total_abandonments;  // Total count

// By hour (0-23)
$analysis->by_hour; // [0 => 12, 1 => 8, ..., 23 => 15]

// By day of week (0=Sunday, 6=Saturday)
$analysis->by_day_of_week; // [0 => 45, 1 => 120, ..., 6 => 80]

// By cart value range
$analysis->by_cart_value_range;
// ['Under $25' => 50, '$25-$50' => 80, '$50-$100' => 120, ...]

// By items count
$analysis->by_items_count;
// ['1 item' => 100, '2-3 items' => 150, '4-5 items' => 50, '6+ items' => 20]

// Common exit points (from metadata)
$analysis->common_exit_points;
// ['shipping' => 80, 'payment' => 50, 'cart' => 30, ...]
```

### Segment Comparison

Compare metrics across customer segments:

```php
$segments = $service->getSegmentComparison($from, $to);

foreach ($segments as $segment) {
    echo "{$segment->segment}: ";
    echo "{$segment->total_carts} carts, ";
    echo "{$segment->conversions} conversions, ";
    echo "\${$segment->avg_value / 100} avg, ";
    echo "{$segment->abandonment_rate}% abandoned";
}
```

## Data Transfer Objects

### DashboardMetrics

```php
use AIArmada\FilamentCart\Data\DashboardMetrics;

class DashboardMetrics extends Data
{
    public int $total_carts;
    public int $active_carts;
    public int $abandoned_carts;
    public int $recovered_carts;
    public int $total_value_cents;
    public float $conversion_rate;
    public float $abandonment_rate;
    public float $recovery_rate;
    public int $average_cart_value_cents;
    public ?float $conversion_rate_change;
    public ?float $abandonment_rate_change;
    public ?float $recovery_rate_change;
}
```

### ConversionFunnel

```php
use AIArmada\FilamentCart\Data\ConversionFunnel;

class ConversionFunnel extends Data
{
    public int $carts_created;
    public int $items_added;
    public int $checkout_started;
    public int $checkout_completed;
    public array $drop_off_rates;

    public static function calculate(
        int $cartsCreated,
        int $itemsAdded,
        int $checkoutStarted,
        int $checkoutCompleted,
    ): self;

    public function getOverallDropOffRate(): float;
    public function getConversionRate(): float;
}
```

### RecoveryMetrics

```php
use AIArmada\FilamentCart\Data\RecoveryMetrics;

class RecoveryMetrics extends Data
{
    public int $total_abandoned;
    public int $recovery_attempts;
    public int $successful_recoveries;
    public int $recovered_revenue_cents;
    public float $recovery_rate;
    public array $by_strategy;

    public static function calculate(...): self;
}
```

### AbandonmentAnalysis

```php
use AIArmada\FilamentCart\Data\AbandonmentAnalysis;

class AbandonmentAnalysis extends Data
{
    public array $by_hour;
    public array $by_day_of_week;
    public array $by_cart_value_range;
    public array $by_items_count;
    public array $common_exit_points;
    public int $total_abandonments;

    public static function fromData(...): self;
}
```

## Analytics Page

The `AnalyticsPage` provides an interactive dashboard with date range selection and export capabilities.

### Features

- **Date Range Selection** — Custom date picker with quick presets
- **Interval Grouping** — Day, week, or month aggregation
- **Real-time Updates** — Widgets respond to date changes via Livewire events
- **Export** — CSV and Excel export options

### URL Parameters

The page persists state in URL parameters for bookmarking and sharing:

```
/admin/cart-analytics?dateFrom=2026-01-01&dateTo=2026-01-15&interval=day
```

### Quick Date Ranges

Built-in presets:
- Last 7 Days
- Last 30 Days
- Last 90 Days

### Widgets

The analytics page includes these widgets:

**Header:**
- `AnalyticsStatsWidget` — Key metrics with comparison

**Footer:**
- `ConversionFunnelWidget` — Visual funnel
- `ValueTrendChartWidget` — Line chart with dual axes
- `AbandonmentAnalysisWidget` — Multi-tab analysis (if feature enabled)
- `RecoveryPerformanceWidget` — Strategy breakdown (if feature enabled)

### Livewire Events

Widgets listen for date range changes:

```php
use Livewire\Attributes\On;

class MyAnalyticsWidget extends Widget
{
    #[On('date-range-updated')]
    public function refresh(): void
    {
        // Widget will re-render with new date range
    }

    public function getData(): array
    {
        $page = $this->getPageInstance();
        $from = $page?->getDateFrom() ?? Carbon::now()->subDays(30);
        $to = $page?->getDateTo() ?? Carbon::now();

        // Use dates...
    }

    private function getPageInstance(): ?AnalyticsPage
    {
        $livewire = $this->getLivewire();
        return $livewire instanceof AnalyticsPage ? $livewire : null;
    }
}
```

## Export Service

Export analytics data to various formats.

```php
use AIArmada\FilamentCart\Services\ExportService;

$export = app(ExportService::class);

// Export to CSV string
$csv = $export->exportMetricsToCsv($from, $to);

// Export to Excel file
$filePath = $export->exportToXlsx($from, $to);
```

### CSV Export

Returns a CSV string with daily metrics:

```csv
Date,Carts Created,Active Carts,Abandoned,Recovered,Total Value,Conversion Rate,Abandonment Rate
2026-01-01,150,120,30,5,1250000,0.15,0.25
2026-01-02,180,145,35,8,1450000,0.18,0.24
```

### Excel Export

Creates an XLSX file with multiple sheets:
- **Daily Metrics** — Day-by-day breakdown
- **Summary** — Aggregate metrics
- **Funnel** — Conversion funnel data
- **Recovery** — Recovery performance

## CartDailyMetrics Model

Analytics are powered by pre-aggregated daily metrics:

```php
use AIArmada\FilamentCart\Models\CartDailyMetrics;

// Query metrics
$metrics = CartDailyMetrics::query()
    ->forOwner()
    ->whereBetween('date', [$from, $to])
    ->whereNull('segment') // Overall metrics
    ->get();

// Segmented metrics
$vipMetrics = CartDailyMetrics::query()
    ->forOwner()
    ->where('segment', 'vip')
    ->whereBetween('date', [$from, $to])
    ->get();
```

**Columns:**
- `date` — Date of metrics
- `segment` — Customer segment (null = overall)
- `carts_created` — New carts
- `carts_active` — Active carts at end of day
- `carts_with_items` — Carts with at least one item
- `total_cart_value_cents` — Sum of cart values
- `average_cart_value_cents` — Average cart value
- `checkouts_started` — Checkout initiations
- `checkouts_completed` — Successful checkouts
- `checkouts_abandoned` — Abandoned checkouts
- `carts_recovered` — Recovered carts
- `recovered_revenue_cents` — Revenue from recovery
- `recovery_emails_sent` — Recovery messages sent

### Aggregation Command

Metrics are aggregated by the scheduled command:

```bash
php artisan cart:aggregate-metrics
```

Schedule it daily:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('cart:aggregate-metrics')
        ->dailyAt('01:00');
}
```

The command aggregates the previous day's data from cart snapshots into the metrics table.

## Database Considerations

### Multi-Database Support

The analytics service handles database driver differences:

```php
$driver = DB::connection()->getDriverName();

$groupBy = match ($interval) {
    'week' => $driver === 'sqlite' 
        ? "strftime('%Y-%W', date)" 
        : 'YEARWEEK(date)',
    'month' => $driver === 'sqlite' 
        ? "strftime('%Y-%m', date)" 
        : "DATE_FORMAT(date, '%Y-%m')",
    default => 'date',
};
```

### Performance

For high-traffic stores, consider:

1. **Indexing** — Ensure indexes on `date`, `segment`, and owner columns
2. **Partitioning** — Partition by date for large datasets
3. **Archival** — Archive old metrics to cold storage
4. **Caching** — Cache expensive calculations

```php
// Example: Cache dashboard metrics for 5 minutes
$metrics = Cache::remember(
    "cart-metrics:{$from}:{$to}",
    300,
    fn () => $service->getDashboardMetrics($from, $to)
);
```

## Custom Analytics

### Adding Custom Metrics

Extend the analytics service:

```php
namespace App\Services;

use AIArmada\FilamentCart\Services\CartAnalyticsService;

class CustomAnalyticsService extends CartAnalyticsService
{
    public function getCustomMetrics(Carbon $from, Carbon $to): array
    {
        // Your custom analytics logic
        return [
            'custom_metric' => $this->calculateCustomMetric($from, $to),
        ];
    }
}
```

Register in a service provider:

```php
$this->app->extend(CartAnalyticsService::class, function ($service, $app) {
    return new CustomAnalyticsService();
});
```

### Custom Widgets

Create widgets using the analytics service:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\ChartWidget;

class CustomAnalyticsChart extends ChartWidget
{
    protected ?string $heading = 'Custom Metric';

    protected function getData(): array
    {
        $service = app(CartAnalyticsService::class);
        $metrics = $service->getDashboardMetrics(
            now()->subDays(30),
            now()
        );

        return [
            'datasets' => [
                [
                    'label' => 'Custom Data',
                    'data' => [$metrics->conversion_rate * 100],
                ],
            ],
            'labels' => ['Conversion Rate %'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
```
