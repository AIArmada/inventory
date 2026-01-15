---
title: Widgets
---

# Widgets

The package includes 17 dashboard widgets for monitoring, analytics, and recovery tracking. Widgets are automatically registered based on enabled features.

## Stats Overview Widgets

### CartStatsWidget

Basic cart statistics for the main dashboard.

```php
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
```

**Displays:**
- Total Carts — All cart sessions
- Active Carts — Carts with items
- Total Items — Sum across all carts
- Cart Value — Total potential revenue

**Features:**
- 4-column layout
- Owner-scoped queries
- Money formatting via Akaunting

### CartStatsOverviewWidget

Enhanced statistics with conversion funnel data and trend charts.

```php
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
```

**Displays:**
- Active Carts (24h) — With 7-day trend chart
- Cart Value — With 7-day trend chart
- Checkouts Started — Last 24 hours
- Abandoned Carts — With abandonment rate
- Recovered — With recovery rate
- Recovery Value — Revenue saved

**Features:**
- Full-width layout
- Trend charts (7 days)
- Percentage calculations
- Color-coded metrics

### LiveStatsWidget

Real-time monitoring with 10-second polling.

```php
use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
```

**Displays:**
- Active Carts — Currently active sessions
- Checkouts — In progress
- Recent Abandonments — Last 30 minutes
- Total Value — With high-value count
- Pending Alerts — Unread alerts
- Fraud Signals — Last 24 hours

**Features:**
- 10-second auto-refresh
- Full-width layout
- Uses `CartMonitor` service
- Color-coded severity

### AnalyticsStatsWidget

Date-range aware statistics for the analytics page.

```php
use AIArmada\FilamentCart\Widgets\AnalyticsStatsWidget;
```

**Displays:**
- Total Carts — In selected period
- Active Carts — With items
- Abandoned — With rate and change indicator
- Recovered — With rate and change indicator
- Conversion Rate — With change indicator
- Total Value — With average cart value

**Features:**
- 30-second polling
- Responds to `date-range-updated` Livewire event
- Shows period-over-period changes
- Trend indicators (up/down arrows)

### CampaignPerformanceWidget

Recovery campaign performance overview.

```php
use AIArmada\FilamentCart\Widgets\CampaignPerformanceWidget;
```

**Displays:**
- Active Campaigns — Currently running
- Messages Sent — All time
- Open Rate — With color thresholds
- Click Rate — With color thresholds
- Carts Recovered — With conversion rate
- Revenue Recovered — All time

**Features:**
- 30-second polling
- Aggregates across all campaigns
- Color-coded rate indicators

## Chart Widgets

### ValueTrendChartWidget

Line chart showing cart value and count trends over time.

```php
use AIArmada\FilamentCart\Widgets\ValueTrendChartWidget;
```

**Displays:**
- Total Value ($) — Left Y-axis
- Cart Count — Right Y-axis

**Features:**
- Dual Y-axis
- Responds to date range changes
- Configurable interval (day/week/month)
- 60-second polling

### StrategyComparisonWidget

Bar chart comparing recovery strategy performance.

```php
use AIArmada\FilamentCart\Widgets\StrategyComparisonWidget;
```

**Displays:**
- Open Rate % — Per strategy
- Click Rate % — Per strategy
- Conversion Rate % — Per strategy

**Features:**
- Grouped bar chart
- Uses `RecoveryAnalytics` service
- 60-second polling

## Funnel Widgets

### ConversionFunnelWidget

Visual funnel showing cart-to-checkout conversion.

```php
use AIArmada\FilamentCart\Widgets\ConversionFunnelWidget;
```

**Stages:**
1. Carts Created
2. Items Added
3. Checkout Started
4. Checkout Completed

**Features:**
- Custom Blade view
- Drop-off calculations
- Percentage at each stage
- Responds to date range

### RecoveryFunnelWidget

Visual funnel showing recovery campaign performance.

```php
use AIArmada\FilamentCart\Widgets\RecoveryFunnelWidget;
```

**Stages:**
1. Carts Targeted
2. Messages Sent
3. Opened
4. Clicked
5. Recovered

**Features:**
- Custom Blade view
- Overall conversion rate
- Drop-off at each stage

## Analysis Widgets

### AbandonmentAnalysisWidget

Multi-dimensional abandonment breakdown.

```php
use AIArmada\FilamentCart\Widgets\AbandonmentAnalysisWidget;
```

**Breakdowns:**
- By Hour — 24-hour distribution
- By Day of Week — Weekly pattern
- By Cart Value Range — Value segments
- By Items Count — Cart size correlation
- Common Exit Points — Where users drop off

**Features:**
- Tabbed interface
- Peak hour/day identification
- Custom Blade view
- Responds to date range

### RecoveryPerformanceWidget

Comprehensive recovery strategy analysis.

```php
use AIArmada\FilamentCart\Widgets\RecoveryPerformanceWidget;
```

**Displays:**
- Summary metrics (abandoned, attempts, recovered)
- Strategy breakdown with rates
- Potential revenue calculation
- Unreached carts count

**Features:**
- Full-width layout
- Color-coded strategies
- Custom Blade view

## Table Widgets

### PendingAlertsWidget

Table of unread alerts requiring attention.

```php
use AIArmada\FilamentCart\Widgets\PendingAlertsWidget;
```

**Columns:**
- Severity — Badge (critical/warning/info)
- Title — With message tooltip
- Event Type — Badge
- Time — Relative timestamp

**Actions:**
- Mark as Read
- View Cart (if linked)
- Bulk mark as read

**Features:**
- 15-second polling
- Priority sorting (critical first)
- Paginated (5/10/25)

### RecentActivityWidget

Real-time cart activity feed.

```php
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
```

**Columns:**
- Session — Cart identifier
- Status — Badge (checkout/active/abandoned/completed)
- Items — Count
- Value — Money formatted
- Updated — Relative timestamp

**Features:**
- 15-second polling
- Raw SQL query for performance
- Owner-scoped via `OwnerQuery` helper

### AbandonedCartsWidget

Table of abandoned carts ready for recovery.

```php
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
```

**Columns:**
- Cart ID — Truncated identifier
- Customer — Email from metadata
- Items — Count
- Value — Money formatted
- Abandoned — Timestamp
- Recovery Attempts — Badge with color
- Age — Time since abandonment

**Actions:**
- View Cart
- Send Recovery Email (if attempts < 3)

**Features:**
- Full-width layout
- 7-day window
- Excludes recovered carts

### FraudDetectionWidget

Table of carts with fraud risk indicators.

```php
use AIArmada\FilamentCart\Widgets\FraudDetectionWidget;
```

**Columns:**
- Cart ID — Truncated identifier
- Risk Level — Badge (high/medium/low)
- Score — Numeric with color coding
- Cart Value — Money formatted
- Items — Count
- Customer — From metadata
- Last Activity — Timestamp

**Actions:**
- View Cart
- Block Cart (high risk only)
- Mark Reviewed

**Features:**
- Full-width layout
- Sorted by fraud score (desc)
- 7-day window
- High/medium risk only

### CollaborativeCartsWidget

Table of shared carts with multiple collaborators.

```php
use AIArmada\FilamentCart\Widgets\CollaborativeCartsWidget;
```

**Columns:**
- Cart ID — Truncated identifier
- Owner — Email from metadata
- Collaborators — Count with badge
- Items — Count
- Cart Value — Money formatted
- Activity — Status badge
- Last Activity — Timestamp
- Created — Date

**Actions:**
- View Cart
- View Collaborators (modal)

**Features:**
- Full-width layout
- 30-day window
- Activity status detection

### RecoveryOptimizerWidget

AI-powered recovery queue with strategy recommendations.

```php
use AIArmada\FilamentCart\Widgets\RecoveryOptimizerWidget;
```

**Columns:**
- Cart ID — Truncated identifier
- Customer — Email from metadata
- Cart Value — Money formatted
- Recommended Strategy — AI-determined badge
- Priority — High/Medium/Low badge
- Abandoned — Time since
- Attempts — Count

**Actions:**
- Discount 10%
- Free Shipping
- Remind
- View Cart

**Strategy Logic:**
- $100+ carts: Reminder → Discount → Personal Outreach
- $50-99 carts: Reminder → Free Shipping
- Under $50: Reminder → Discount

**Priority Logic:**
- High: $100+ and abandoned < 24h
- Medium: $50+ or abandoned < 12h
- Low: Everything else

## Widget Registration

Widgets are registered in `FilamentCartPlugin::getWidgets()`:

```php
public function getWidgets(): array
{
    $widgets = [
        Widgets\CartStatsWidget::class,
    ];

    if ($this->hasFeature('recovery')) {
        $widgets[] = Widgets\RecoveryPerformanceWidget::class;
        $widgets[] = Widgets\CampaignPerformanceWidget::class;
        // ...
    }

    if ($this->hasFeature('monitoring')) {
        $widgets[] = Widgets\LiveStatsWidget::class;
        $widgets[] = Widgets\PendingAlertsWidget::class;
        // ...
    }

    return $widgets;
}
```

## Custom Widget Views

Several widgets use custom Blade views located in `resources/views/widgets/`:

- `conversion-funnel.blade.php`
- `recovery-funnel.blade.php`
- `abandonment-analysis.blade.php`
- `recovery-performance.blade.php`
- `collaborators-modal.blade.php`

### Publishing Views

```bash
php artisan vendor:publish --tag=filament-cart-views
```

## Extending Widgets

### Override a Widget

Create your own widget extending the base:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentCart\Widgets\CartStatsWidget as BaseWidget;

class CartStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom stat
        $stats[] = Stat::make('Custom Metric', $this->getCustomValue());
        
        return $stats;
    }
}
```

Register your widget in the panel provider:

```php
->widgets([
    \App\Filament\Widgets\CartStatsWidget::class,
])
```

### Create a Custom Widget

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomCartWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('VIP Carts', Cart::query()->forOwner()
                ->where('subtotal', '>=', 50000) // $500+
                ->count())
                ->description('High-value customers')
                ->color('warning'),
        ];
    }
}
```

## Page Widget Assignments

Widgets are assigned to specific pages:

### CartDashboard

- CartStatsOverviewWidget
- AbandonedCartsWidget
- FraudDetectionWidget
- RecoveryOptimizerWidget
- CollaborativeCartsWidget

### AnalyticsPage

- AnalyticsStatsWidget
- ConversionFunnelWidget
- ValueTrendChartWidget
- AbandonmentAnalysisWidget
- RecoveryPerformanceWidget

### LiveDashboardPage

- LiveStatsWidget
- PendingAlertsWidget
- RecentActivityWidget

### RecoverySettingsPage

- CampaignPerformanceWidget
- RecoveryFunnelWidget
- StrategyComparisonWidget
