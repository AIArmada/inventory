---
title: Pages & Widgets
---

# Pages & Widgets

## Default Components

The plugin registers these components automatically:

| Type | Component | Description |
|------|-----------|-------------|
| Page | AnalyticsDashboardPage | Revenue analytics dashboard |
| Widget | ChipStatsWidget | Core payment metrics |
| Widget | RevenueChartWidget | Revenue over time chart |
| Widget | RecentTransactionsWidget | Latest transactions table |

## Dashboard Page

### AnalyticsDashboardPage

Comprehensive analytics dashboard with revenue metrics and charts.

**Features:**
- Period filtering (today, 7d, 30d, 90d, custom)
- Revenue totals and growth comparison
- Transaction count and average value
- Payment method distribution

**Customization:**

```php
<?php

namespace App\Filament\Pages;

use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage as BasePage;

class AnalyticsDashboardPage extends BasePage
{
    protected function getHeaderWidgets(): array
    {
        return [
            CustomRevenueWidget::class,
            ...parent::getHeaderWidgets(),
        ];
    }
}
```

## Registered Widgets

### ChipStatsWidget

Core metrics stats overview:

- Total Revenue (sum of paid purchases)
- Transaction Count
- Average Transaction Value
- Success Rate

**Customization:**

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\FilamentChip\Widgets\ChipStatsWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChipStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            ...parent::getStats(),
            Stat::make('Custom Metric', $this->calculateCustomMetric())
                ->icon('heroicon-o-star'),
        ];
    }
}
```

### RevenueChartWidget

Line/area chart showing revenue over time.

- Period selection (7d, 30d, 90d, 1y)
- Comparison with previous period
- Currency formatting

### RecentTransactionsWidget

Table widget showing latest transactions with amount, status, and timestamp.

## Optional Widgets

These widgets are available but not registered by default. Add them to your panel or page manually:

| Widget | Description |
|--------|-------------|
| AccountBalanceWidget | Current account balance display |
| AccountTurnoverWidget | Account turnover metrics |
| BankAccountStatusWidget | Bank account verification status |
| PaymentMethodsWidget | Payment method distribution chart |
| PayoutAmountWidget | Total payout amounts |
| PayoutStatsWidget | Payout statistics overview |
| RecentPayoutsWidget | Latest payouts table |
| TokenStatsWidget | Saved token statistics |

### Adding Optional Widgets

**On a Dashboard Page:**

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use AIArmada\FilamentChip\Widgets\PayoutStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            PayoutStatsWidget::class,
            PaymentMethodsWidget::class,
        ];
    }
}
```

## Widget Customization

### Column Span

Control widget column span:

```php
class ChipStatsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    // Options: 1, 2, 3, 'full', ['md' => 2, 'xl' => 3]
}
```

### Sort Order

Control widget display order:

```php
class ChipStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
}

class RevenueChartWidget extends BaseWidget
{
    protected static ?int $sort = 2;
}
