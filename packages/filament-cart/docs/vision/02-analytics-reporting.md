---
title: Phase 1 - Analytics & Reporting
---

# Phase 1: Enhanced Analytics & Reporting

> **Status:** Not Started  
> **Priority:** High  
> **Estimated Effort:** 1 Sprint

---

## Overview

Transform the basic cart statistics into a comprehensive analytics system with pre-aggregated metrics, conversion funnel tracking, cohort analysis, and exportable reports.

---

## Components

### 1. CartDailyMetrics Model

Pre-aggregated daily metrics for fast dashboard queries.

```php
// Migration: create_cart_daily_metrics_table
Schema::create('cart_daily_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->date('date')->index();
    $table->string('segment')->nullable()->index(); // customer segment
    
    // Cart counts
    $table->unsignedInteger('carts_created')->default(0);
    $table->unsignedInteger('carts_active')->default(0);
    $table->unsignedInteger('carts_empty')->default(0);
    $table->unsignedInteger('carts_with_items')->default(0);
    
    // Checkout funnel
    $table->unsignedInteger('checkouts_started')->default(0);
    $table->unsignedInteger('checkouts_completed')->default(0);
    $table->unsignedInteger('checkouts_abandoned')->default(0);
    
    // Recovery metrics
    $table->unsignedInteger('recovery_emails_sent')->default(0);
    $table->unsignedInteger('carts_recovered')->default(0);
    $table->unsignedBigInteger('recovered_revenue_cents')->default(0);
    
    // Value metrics
    $table->unsignedBigInteger('total_cart_value_cents')->default(0);
    $table->unsignedBigInteger('average_cart_value_cents')->default(0);
    $table->unsignedInteger('total_items')->default(0);
    $table->decimal('average_items_per_cart', 8, 2)->default(0);
    
    // Fraud metrics
    $table->unsignedInteger('fraud_alerts_high')->default(0);
    $table->unsignedInteger('fraud_alerts_medium')->default(0);
    $table->unsignedInteger('carts_blocked')->default(0);
    
    // Collaborative metrics
    $table->unsignedInteger('collaborative_carts')->default(0);
    $table->unsignedInteger('total_collaborators')->default(0);
    
    $table->timestamps();
    
    $table->unique(['date', 'segment']);
});
```

### 2. CartAnalyticsService

Service for retrieving and calculating analytics metrics.

```php
class CartAnalyticsService
{
    // Dashboard metrics
    public function getDashboardMetrics(Carbon $from, Carbon $to): DashboardMetrics;
    
    // Conversion funnel
    public function getConversionFunnel(Carbon $from, Carbon $to): ConversionFunnel;
    
    // Recovery metrics
    public function getRecoveryMetrics(Carbon $from, Carbon $to): RecoveryMetrics;
    
    // Value trends
    public function getValueTrends(Carbon $from, Carbon $to, string $interval = 'day'): array;
    
    // Abandonment analysis
    public function getAbandonmentAnalysis(Carbon $from, Carbon $to): AbandonmentAnalysis;
    
    // Segment comparison
    public function getSegmentComparison(Carbon $from, Carbon $to): array;
}
```

### 3. MetricsAggregator

Service for aggregating raw cart data into daily metrics.

```php
class MetricsAggregator
{
    public function aggregateForDate(Carbon $date, ?string $segment = null): CartDailyMetrics;
    public function aggregateTotals(Carbon $from, Carbon $to): AggregatedMetrics;
    public function backfill(Carbon $from, Carbon $to): int;
}
```

### 4. ExportService

Service for exporting analytics data.

```php
class ExportService
{
    public function exportToCSV(string $type, Carbon $from, Carbon $to): string;
    public function exportToPDF(string $type, Carbon $from, Carbon $to): string;
    public function getExportableTypes(): array;
}
```

### 5. DTOs

```php
// DashboardMetrics
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
    public float $average_cart_value;
}

// ConversionFunnel
class ConversionFunnel extends Data
{
    public int $carts_created;
    public int $items_added;
    public int $checkout_started;
    public int $payment_initiated;
    public int $completed;
    public array $drop_off_rates;
}

// RecoveryMetrics
class RecoveryMetrics extends Data
{
    public int $total_abandoned;
    public int $recovery_attempts;
    public int $successful_recoveries;
    public int $recovered_revenue_cents;
    public float $recovery_rate;
    public array $by_strategy;
}

// AbandonmentAnalysis
class AbandonmentAnalysis extends Data
{
    public array $by_hour;
    public array $by_day_of_week;
    public array $by_cart_value_range;
    public array $by_items_count;
    public array $common_exit_points;
}
```

---

## Filament Components

### AnalyticsPage

New dedicated analytics page with comprehensive metrics.

```php
class AnalyticsPage extends Page
{
    protected static ?string $slug = 'cart-analytics';
    protected static ?string $title = 'Cart Analytics';
    
    public string $period = '30'; // days
    
    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsStatsWidget::class,
            ConversionFunnelWidget::class,
        ];
    }
    
    protected function getFooterWidgets(): array
    {
        return [
            ValueTrendChartWidget::class,
            AbandonmentAnalysisWidget::class,
            RecoveryPerformanceWidget::class,
        ];
    }
}
```

### New Widgets

1. **AnalyticsStatsWidget** - Enhanced stats with period comparison
2. **ConversionFunnelWidget** - Visual funnel chart
3. **ValueTrendChartWidget** - Line chart of cart values over time
4. **AbandonmentAnalysisWidget** - Heatmap of abandonment patterns
5. **RecoveryPerformanceWidget** - Recovery campaign performance

### Export Actions

Add export actions to existing pages and widgets:

```php
Action::make('export_csv')
    ->label('Export CSV')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(fn () => $this->export('csv'));

Action::make('export_pdf')
    ->label('Export Report')
    ->icon('heroicon-o-document')
    ->action(fn () => $this->export('pdf'));
```

---

## Commands

### AggregateMetricsCommand

```bash
php artisan cart:aggregate-metrics --date=2025-01-01
php artisan cart:aggregate-metrics --from=2025-01-01 --to=2025-01-31
php artisan cart:aggregate-metrics --backfill=90  # Last 90 days
```

---

## Configuration

```php
// config/filament-cart.php
'analytics' => [
    'retention_days' => 365,
    'aggregation_schedule' => 'daily', // hourly, daily
    'default_period_days' => 30,
    'export_formats' => ['csv', 'pdf'],
],
```

---

## Files to Create

| File | Type | Description |
|------|------|-------------|
| `database/migrations/..._create_cart_daily_metrics_table.php` | Migration | Daily metrics table |
| `src/Models/CartDailyMetrics.php` | Model | Daily metrics model |
| `src/Services/CartAnalyticsService.php` | Service | Analytics API |
| `src/Services/MetricsAggregator.php` | Service | Data aggregation |
| `src/Services/ExportService.php` | Service | Export functionality |
| `src/Data/DashboardMetrics.php` | DTO | Dashboard metrics |
| `src/Data/ConversionFunnel.php` | DTO | Funnel data |
| `src/Data/RecoveryMetrics.php` | DTO | Recovery metrics |
| `src/Data/AbandonmentAnalysis.php` | DTO | Abandonment data |
| `src/Pages/AnalyticsPage.php` | Page | Analytics dashboard |
| `src/Widgets/AnalyticsStatsWidget.php` | Widget | Enhanced stats |
| `src/Widgets/ConversionFunnelWidget.php` | Widget | Funnel visualization |
| `src/Widgets/ValueTrendChartWidget.php` | Widget | Value trends |
| `src/Widgets/AbandonmentAnalysisWidget.php` | Widget | Abandonment patterns |
| `src/Widgets/RecoveryPerformanceWidget.php` | Widget | Recovery metrics |
| `src/Commands/AggregateMetricsCommand.php` | Command | CLI aggregation |
| `resources/views/pages/analytics.blade.php` | View | Analytics page view |

---

## Tests

- `CartAnalyticsServiceTest` - Service unit tests
- `MetricsAggregatorTest` - Aggregation logic tests
- `ExportServiceTest` - Export functionality tests
- `AnalyticsPageTest` - Page integration tests
