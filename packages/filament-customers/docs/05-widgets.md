---
title: Widgets
---

# Widgets

The plugin provides dashboard widgets for customer analytics and insights.

## Customer Stats Widget

### Overview

The Customer Stats Widget displays key customer metrics with trend indicators.

**Location**: Dashboard (default)
**Polling**: 30 seconds
**Owner Scoped**: Yes

### Metrics

#### Total Customers
- Count of all customers
- Week-over-week trend percentage
- Sparkline chart showing last week vs this week
- Color: Green (increase) or Red (decrease)

#### New This Month
- Customers who joined this month
- No trend comparison
- Icon: User Plus
- Color: Info (blue)

#### Active Customers
- Customers with status = Active
- No trend comparison
- Icon: Check Circle
- Color: Success (green)

#### Marketing Opt-In
- Count of customers accepting marketing
- Percentage of total customers
- Icon: Envelope
- Color: Warning (amber)

### Usage

The widget is automatically added when you register the plugin:

```php
FilamentCustomersPlugin::make()
```

### Customization

#### Change Polling Interval

```php
use AIArmada\FilamentCustomers\Widgets\CustomerStatsWidget;

class CustomCustomerStatsWidget extends CustomerStatsWidget
{
    protected ?string $pollingInterval = '60s'; // Every minute
}
```

#### Modify Stats

```php
class CustomCustomerStatsWidget extends CustomerStatsWidget
{
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom stat
        $stats[] = Stat::make('Custom Metric', '123')
            ->description('Custom description')
            ->color('info');
        
        return $stats;
    }
}
```

#### Disable Widget

Remove from plugin registration or set visibility:

```php
class CustomCustomerStatsWidget extends CustomerStatsWidget
{
    protected static bool $isDiscovered = false;
}
```

## Recent Customers Widget

### Overview

The Recent Customers Widget shows a list of recently registered customers.

**Location**: Dashboard (default)
**Column Span**: Full width
**Sort Order**: 2
**Limit**: 10 recent customers
**Owner Scoped**: Yes

### Columns

- **Customer**: Name with email description
- **Status**: Customer status badge
- **Marketing**: Whether accepts marketing
- **Joined**: Registration date

### Features

- Shows most recent customers first
- Sorted by created_at descending
- No pagination (shows 10 only)
- Clicking row navigates to customer view

### Usage

Auto-registered with plugin:

```php
FilamentCustomersPlugin::make()
```

### Customization

#### Change Limit

```php
use AIArmada\FilamentCustomers\Widgets\TopCustomersWidget;

class CustomRecentCustomersWidget extends TopCustomersWidget
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->query(
                CustomersOwnerScope::applyToOwnedQuery(Customer::query())
                    ->latest()
                    ->limit(20) // Show 20
            );
    }
}
```

#### Change Sort Order

```php
class CustomRecentCustomersWidget extends TopCustomersWidget
{
    protected static ?int $sort = 10; // Move to bottom
}
```

## Creating Custom Widgets

### Stats Widget Example

```php
namespace App\Filament\Widgets;

use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomCustomerWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = CustomersOwnerScope::applyToOwnedQuery(
            Customer::query()
        );
        
        $active = $query->clone()->where('status', 'active')->count();
        $marketing = $query->clone()->where('accepts_marketing', true)->count();
        
        return [
            Stat::make('Active Customers', $active)
                ->description('Currently active')
                ->color('success'),
                
            Stat::make('Marketing Opt-In', $marketing)
                ->description('Accepting marketing')
                ->color('info'),
        ];
    }
}
```

### Table Widget Example

```php
namespace App\Filament\Widgets;

use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Customers';
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                CustomersOwnerScope::applyToOwnedQuery(Customer::query())
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer'),
                    
                Tables\Columns\TextColumn::make('email'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y'),
            ]);
    }
}
```

### Chart Widget Example

```php
namespace App\Filament\Widgets;

use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Filament\Widgets\ChartWidget;

class CustomerGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'Customer Growth';
    
    protected function getData(): array
    {
        $last6Months = collect(range(5, 0))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $count = CustomersOwnerScope::applyToOwnedQuery(Customer::query())
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
                
            return [
                'month' => $date->format('M Y'),
                'count' => $count,
            ];
        });
        
        return [
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $last6Months->pluck('count')->toArray(),
                ],
            ],
            'labels' => $last6Months->pluck('month')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
```

## Widget Best Practices

### Owner Scoping

**Always** apply owner scoping to widget queries:

```php
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;

$query = CustomersOwnerScope::applyToOwnedQuery(Customer::query());
```

### Performance

For expensive queries, use caching:

```php
protected function getStats(): array
{
    return cache()->remember(
        'customer-stats-' . OwnerContext::resolve()?->getKey(),
        now()->addMinutes(5),
        fn () => $this->calculateStats()
    );
}
```

### Polling

Disable polling for static data:

```php
protected ?string $pollingInterval = null;
```

Use longer intervals for expensive queries:

```php
protected ?string $pollingInterval = '5m'; // Every 5 minutes
```

### Visibility

Control widget visibility:

```php
public static function canView(): bool
{
    return auth()->user()->can('viewCustomerStats');
}
```

## Registering Widgets

Widgets are auto-discovered if in `app/Filament/Widgets` directory.

Or register manually:

```php
public function panel(Panel $panel): Panel
{
    return $panel->widgets([
        CustomerStatsWidget::class,
        TopCustomersWidget::class,
        CustomCustomerWidget::class,
    ]);
}
```

## Next Steps

- [Troubleshooting](99-troubleshooting.md) - Common issues
