---
title: Widgets
---

# Widgets

The package provides 9 dashboard widgets for inventory monitoring and analytics.

## Inventory Stats Widget

Overview statistics for quick health check.

```php
// Enable/disable
'features' => [
    'stats_widget' => true,
],
```

### Stats Displayed

| Stat | Description |
|------|-------------|
| Active Locations | Count of active warehouses |
| Total SKUs | Unique products tracked |
| Total On Hand | Physical stock quantity |
| Total Reserved | Allocated to orders |
| Total Available | Ready to sell |
| Low Stock Items | Below reorder point |

### Polling

Refreshes every 30 seconds by default.

## KPI Widget

Key performance indicators with trend charts.

```php
'features' => [
    'kpi_widget' => true,
],
```

### Metrics

| KPI | Description | Color Thresholds |
|-----|-------------|------------------|
| Inventory Turnover | Annual turnover ratio | ≥6x green, ≥3x blue, ≥1x warning, <1x danger |
| Days On Hand | Average inventory age | ≤30d green, ≤60d blue, ≤90d warning, >90d danger |
| Fill Rate | Order fulfillment % | ≥98% green, ≥95% blue, ≥90% warning, <90% danger |
| Inventory Accuracy | From cycle counts | ≥99% green, ≥97% blue, ≥95% warning, <95% danger |

### Features

- 3-month trend charts
- Month-over-month change indicators
- Color-coded status

## Low Inventory Alerts Widget

Table of items below reorder point.

```php
'features' => [
    'low_stock_widget' => true,
],
```

### Columns

- Location
- Product Type
- Product ID
- On Hand
- Reserved
- Available
- Reorder Point
- Deficit (how much below reorder point)

### Features

- Sorted by deficit (worst first)
- Paginated (5, 10, 25)
- Refreshes every 60 seconds

## Expiring Batches Widget

Batches approaching expiry date.

```php
'features' => [
    'expiring_batches_widget' => true,
],
```

### Configuration

```php
'tables' => [
    'expiry_warning_days' => 30,
],
```

### Columns

- Batch Number
- Product Type
- Location
- Available Quantity
- Expiry Date
- Days Left
- Status

### Color Coding

- Expired: Red
- ≤7 days: Warning
- >7 days: Gray

## Reorder Suggestions Widget

AI-generated reorder recommendations.

```php
'features' => [
    'reorder_suggestions_widget' => true,
],
```

### Columns

- Product Type
- Location
- Current Stock
- Reorder Point
- Suggested Quantity
- Expected Stockout Date
- Urgency
- Status

### Actions

- **Approve** — Mark suggestion as approved
- **Reject** — Dismiss suggestion

## Backorders Widget

Open backorder tracking.

```php
'features' => [
    'backorders_widget' => true,
],
```

### Columns

- Product Type
- Order ID
- Location
- Requested
- Fulfilled
- Remaining
- Promised Date
- Priority
- Status

## Inventory Valuation Widget

Total inventory value by costing method.

```php
'features' => [
    'valuation_widget' => true,
],

'defaults' => [
    'costing_method' => 'fifo', // fifo, lifo, average, specific
],
```

### Stats

- Total Inventory Value
- Total Units
- Unique SKUs
- Average Unit Cost

## Movement Trends Chart

Line chart of daily inventory movements.

```php
'features' => [
    'movement_trends_chart' => true,
],
```

### Data Series

- Receipts (green)
- Shipments (blue)
- Adjustments (amber)
- Transfers (purple)

### Features

- 30-day history
- Interactive legend
- Hover tooltips

## ABC Analysis Chart

Pareto classification doughnut chart.

```php
'features' => [
    'abc_analysis_chart' => true,
],
```

### Classification

| Class | Value % | Description |
|-------|---------|-------------|
| A | 80% | High-value items (few SKUs) |
| B | 15% | Medium-value items |
| C | 5% | Low-value items (many SKUs) |

### Features

- SKU count by class
- Value percentage breakdown
- Interactive legend

## Widget Placement

Widgets are automatically registered with the panel. To customize placement:

```php
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;

class Dashboard extends BaseDashboard
{
    protected function getWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
            // ... other widgets
        ];
    }
}
```

## Caching

Stats are cached to improve performance:

```php
'cache' => [
    'stats_ttl' => 60, // seconds
],
```

To clear the cache programmatically:

```php
use AIArmada\FilamentInventory\Services\InventoryStatsAggregator;

app(InventoryStatsAggregator::class)->clearCache();
```
