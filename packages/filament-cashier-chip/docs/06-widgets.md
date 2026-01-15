---
title: Dashboard Widgets
---

# Dashboard Widgets

Analytics widgets for monitoring subscription metrics on your Filament dashboard.

## Available Widgets

### MRRWidget

Monthly Recurring Revenue with trend analysis.

**Displays:**
- Current MRR value
- Percentage change from last month
- 6-month sparkline chart

**Calculation:**
- Sums all active subscription amounts
- Normalizes to monthly (yearly ÷ 12, weekly × 4.33)
- Subtracts active discounts

```php
// MRR calculation
$monthlyAmount = $this->normalizeToMonthly(
    $subscription->items->sum('unit_amount'),
    $subscription->billing_interval,
    $subscription->billing_interval_count
);
```

### ActiveSubscribersWidget

Total count of active subscribers.

**Displays:**
- Current active subscriber count
- Comparison with previous period
- Trend indicator (up/down/stable)

**Counts subscriptions with status:**
- Active
- Trialing
- Past Due (still active)

### ChurnRateWidget

Monthly churn rate percentage.

**Displays:**
- Current churn rate
- Month-over-month comparison
- Trend indicator

**Calculation:**
```
Churn Rate = (Canceled This Month / Active Start of Month) × 100
```

### TrialConversionsWidget

Trial-to-paid conversion rate.

**Displays:**
- Conversion percentage
- Trial → Paid count
- Comparison with previous period

**Calculation:**
```
Conversion Rate = (Trials Converted / Trials Expired) × 100
```

### AttentionRequiredWidget

Count of subscriptions needing attention.

**Displays:**
- Past due subscription count
- Incomplete payment count
- Click to view problem subscriptions

**Flags subscriptions with:**
- Past due status
- Incomplete status
- Failed renewal attempts

### RevenueChartWidget

Revenue trend visualization over time.

**Displays:**
- Line chart of monthly revenue
- 6 or 12 month history
- Hover for exact values

### SubscriptionDistributionWidget

Subscriptions by plan/type distribution.

**Displays:**
- Pie or bar chart
- Breakdown by subscription type
- Percentage of each plan

## Enabling Widgets

### All Widgets

```php
FilamentCashierChipPlugin::make()
    ->dashboardWidgets(true);
```

### Individual Widgets

Via config:

```php
// config/filament-cashier-chip.php
'features' => [
    'dashboard' => [
        'widgets' => [
            'mrr' => true,
            'active_subscribers' => true,
            'churn_rate' => true,
            'attention_required' => true,
            'revenue_chart' => true,
            'subscription_distribution' => true,
            'trial_conversions' => true,
        ],
    ],
],
```

### On Specific Dashboard

Register widgets manually:

```php
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected function getWidgets(): array
    {
        return [
            MRRWidget::class,
            ActiveSubscribersWidget::class,
        ];
    }
}
```

## Widget Configuration

### Sort Order

```php
// In the widget class
protected static ?int $sort = 1;
```

Default sort order:
1. MRRWidget
2. ActiveSubscribersWidget
3. ChurnRateWidget
4. AttentionRequiredWidget
5. TrialConversionsWidget
6. RevenueChartWidget
7. SubscriptionDistributionWidget

### Column Span

Stats widgets use single column:

```php
protected function getColumns(): int
{
    return 1;
}
```

Chart widgets span multiple columns:

```php
protected function getColumns(): int
{
    return 2;
}
```

## Customizing Widgets

### Extend a Widget

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentCashierChip\Widgets\MRRWidget as BaseMRRWidget;

class MRRWidget extends BaseMRRWidget
{
    protected static ?int $sort = 0; // First widget
    
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom stat
        $stats[] = Stat::make('Target', '$10,000')
            ->description('Monthly goal');
            
        return $stats;
    }
}
```

### Custom Calculations

Override calculation methods:

```php
class MRRWidget extends BaseMRRWidget
{
    private function calculateMRR(): int
    {
        // Custom MRR calculation
        return Subscription::query()
            ->whereActive()
            ->whereNull('trial_ends_at') // Exclude trials
            ->sum('monthly_amount');
    }
}
```

### Custom Styling

```php
protected function getStats(): array
{
    return [
        Stat::make('MRR', $this->formatCurrency($mrr))
            ->description($trend['description'])
            ->descriptionIcon($trend['icon'])
            ->color($trend['color'])
            ->chart($this->getMRRChart())
            ->extraAttributes([
                'class' => 'bg-gradient-to-r from-primary-50 to-primary-100',
            ]),
    ];
}
```

## Owner Scoping

All widgets automatically apply owner scoping:

```php
$subscriptions = CashierChipOwnerScope::apply(
    Subscription::query()
)->whereActive()->get();
```

This ensures:
- Multi-tenant data isolation
- Each tenant sees only their metrics
- Consistent with resource scoping

## Performance Considerations

### Caching

For large datasets, consider caching:

```php
private function calculateMRR(): int
{
    return cache()->remember('mrr_' . tenant()->id, 3600, function () {
        return $this->computeMRR();
    });
}
```

### Query Optimization

Widgets use eager loading:

```php
$subscriptions = Subscription::query()
    ->whereActive()
    ->with('items') // Eager load items
    ->get();
```

### Polling

Widgets don't poll by default. To add live updates:

```php
protected static ?string $pollingInterval = '30s';
```

## Chart Widgets

### Revenue Chart

Uses Filament's chart components:

```php
protected function getData(): array
{
    return [
        'datasets' => [
            [
                'label' => 'Revenue',
                'data' => $this->getMonthlyRevenue(),
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
            ],
        ],
        'labels' => $this->getMonthLabels(),
    ];
}
```

### Subscription Distribution

```php
protected function getData(): array
{
    $distribution = $this->getDistribution();
    
    return [
        'datasets' => [
            [
                'data' => $distribution->pluck('count')->toArray(),
                'backgroundColor' => $this->getColors(),
            ],
        ],
        'labels' => $distribution->pluck('type')->toArray(),
    ];
}
```

## Testing Widgets

```php
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use Livewire\Livewire;

it('displays MRR widget', function () {
    // Create test subscriptions
    Subscription::factory()->active()->create([
        'billing_interval' => 'month',
    ]);
    
    Livewire::test(MRRWidget::class)
        ->assertSee('Monthly Recurring Revenue');
});
```

## Next Steps

- [Resources](04-resources.md) – Admin panel resources
- [Billing Portal](05-billing-portal.md) – Customer self-service
