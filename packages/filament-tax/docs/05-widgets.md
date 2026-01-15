---
title: Widgets
---

# Widgets

The plugin includes three dashboard widgets for tax monitoring and overview.

## Enabling Widgets

Widgets are enabled by default. Control via plugin configuration:

```php
FilamentTaxPlugin::make()
    ->widgets(true);  // Enable all widgets
    
FilamentTaxPlugin::make()
    ->widgets(false); // Disable all widgets
```

Or via configuration file:

```php
// config/filament-tax.php
'features' => [
    'widgets' => true,
],
```

## Tax Stats Widget

Overview statistics showing counts of tax entities.

### Display

```
┌─────────────────────────────────────────────────────────────┐
│ Tax Overview                                                 │
├──────────────┬──────────────┬──────────────┬────────────────┤
│ 🌍 5         │ 📊 12        │ 🏷️ 4        │ 🛡️ 8          │
│ Tax Zones    │ Tax Rates    │ Tax Classes  │ Exemptions     │
│ Active       │ Configured   │ Configured   │ Active         │
└──────────────┴──────────────┴──────────────┴────────────────┘
```

### Stats Shown

| Stat | Description | Query |
|------|-------------|-------|
| Tax Zones | Count of active zones | `TaxZone::active()->count()` |
| Tax Rates | Count of active rates | `TaxRate::active()->count()` |
| Tax Classes | Count of active classes | `TaxClass::active()->count()` |
| Exemptions | Count of active exemptions | `TaxExemption::approved()->active()->count()` |

### Implementation

```php
namespace AIArmada\FilamentTax\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaxStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Tax Zones', TaxZone::active()->count())
                ->description('Active zones')
                ->icon('heroicon-o-globe-alt'),
                
            Stat::make('Tax Rates', TaxRate::active()->count())
                ->description('Configured rates')
                ->icon('heroicon-o-calculator'),
                
            Stat::make('Tax Classes', TaxClass::active()->count())
                ->description('Configured classes')
                ->icon('heroicon-o-tag'),
                
            Stat::make('Exemptions', TaxExemption::approved()->active()->count())
                ->description('Active exemptions')
                ->icon('heroicon-o-shield-check'),
        ];
    }
}
```

---

## Expiring Exemptions Widget

Table widget showing exemptions that will expire within the next 30 days.

### Display

```
┌─────────────────────────────────────────────────────────────┐
│ Expiring Exemptions                                          │
├──────────────────┬───────────────┬─────────────┬────────────┤
│ Customer         │ Zone          │ Expires     │ Actions    │
├──────────────────┼───────────────┼─────────────┼────────────┤
│ Acme Corp        │ Malaysia      │ Jan 15, 2025│ [View]     │
│ Tech Solutions   │ All Zones     │ Jan 20, 2025│ [View]     │
│ Global Trade Ltd │ Singapore     │ Feb 1, 2025 │ [View]     │
└──────────────────┴───────────────┴─────────────┴────────────┘
```

### Columns

| Column | Description |
|--------|-------------|
| Customer | Exemptable entity identifier |
| Zone | Tax zone name or "All Zones" |
| Expires | Expiration date |
| Actions | View exemption link |

### Implementation

```php
namespace AIArmada\FilamentTax\Widgets;

use Filament\Tables;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringExemptionsWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Expiring Exemptions';
    
    protected function getTableQuery(): Builder
    {
        return TaxExemption::query()
            ->approved()
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>=', now())
            ->orderBy('expires_at');
    }
    
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('exemptable_id')
                ->label('Customer'),
                
            Tables\Columns\TextColumn::make('zone.name')
                ->label('Zone')
                ->default('All Zones'),
                
            Tables\Columns\TextColumn::make('expires_at')
                ->label('Expires')
                ->date(),
        ];
    }
    
    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view')
                ->url(fn ($record) => TaxExemptionResource::getUrl('edit', ['record' => $record])),
        ];
    }
}
```

### Configuration

The 30-day window is hardcoded. To customize, extend the widget:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentTax\Widgets\ExpiringExemptionsWidget as BaseWidget;

class ExpiringExemptionsWidget extends BaseWidget
{
    protected int $daysAhead = 60; // Extend to 60 days
    
    protected function getTableQuery(): Builder
    {
        return TaxExemption::query()
            ->approved()
            ->where('expires_at', '<=', now()->addDays($this->daysAhead))
            ->where('expires_at', '>=', now())
            ->orderBy('expires_at');
    }
}
```

---

## Zone Coverage Widget

Visual overview of all tax zones and their configured rates.

### Display

```
┌─────────────────────────────────────────────────────────────┐
│ Zone Coverage                                                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🌍 Malaysia (MY)                           [Default] ✓  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Countries: MY                                           │ │
│ │ States: —                                               │ │
│ │ Postcodes: —                                            │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Rates:                                                  │ │
│ │ • SST 6% (standard) - 6.00%                            │ │
│ │ • SST 6% (digital) - 6.00%                             │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🌍 Singapore (SG)                                    ✓  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Countries: SG                                           │ │
│ │ Rates:                                                  │ │
│ │ • GST (standard) - 9.00%                               │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🌍 EU Zone (EU)                                      ✗  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Countries: DE, FR, IT, ES, NL, BE, AT                  │ │
│ │ Rates:                                                  │ │
│ │ • VAT (standard) - 20.00%                              │ │
│ │ • VAT Reduced (reduced) - 10.00%                       │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Features

- Shows all active zones with their geographic criteria
- Lists all rates per zone with tax class and percentage
- Indicates default zone
- Shows active/inactive status
- Priority-ordered display

### Implementation

The widget uses a Blade view for flexible rendering:

```php
namespace AIArmada\FilamentTax\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class ZoneCoverageWidget extends Widget
{
    protected static string $view = 'filament-tax::widgets.zone-coverage';
    
    protected int | string | array $columnSpan = 'full';
    
    public function getZones(): Collection
    {
        return TaxZone::query()
            ->with(['rates' => fn ($q) => $q->active()])
            ->orderBy('priority', 'desc')
            ->get();
    }
}
```

### Blade Template

Located at `resources/views/widgets/zone-coverage.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Zone Coverage">
        <div class="space-y-4">
            @foreach ($this->getZones() as $zone)
                <div class="border rounded-lg p-4 {{ $zone->is_active ? '' : 'opacity-50' }}">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-bold">
                            🌍 {{ $zone->name }} ({{ $zone->code }})
                        </h3>
                        <div class="flex gap-2">
                            @if ($zone->is_default)
                                <span class="badge badge-primary">Default</span>
                            @endif
                            <span class="{{ $zone->is_active ? 'text-success' : 'text-danger' }}">
                                {{ $zone->is_active ? '✓' : '✗' }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-2">
                        <div>Countries: {{ collect($zone->countries)->join(', ') ?: '—' }}</div>
                        @if ($zone->states)
                            <div>States: {{ collect($zone->states)->join(', ') }}</div>
                        @endif
                        @if ($zone->postcodes)
                            <div>Postcodes: {{ collect($zone->postcodes)->join(', ') }}</div>
                        @endif
                    </div>
                    
                    @if ($zone->rates->isNotEmpty())
                        <div class="mt-2">
                            <div class="font-medium">Rates:</div>
                            <ul class="list-disc list-inside text-sm">
                                @foreach ($zone->rates as $rate)
                                    <li>
                                        {{ $rate->name }} ({{ $rate->tax_class }}) - {{ $rate->getFormattedRate() }}
                                        @if ($rate->is_compound) [compound] @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="text-sm text-gray-400 italic">No rates configured</div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Widget Placement

Widgets appear on the dashboard by default. To control placement:

### Dashboard Sort

```php
class TaxStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10; // Lower = higher on dashboard
}
```

### Column Span

```php
// Full width
protected int | string | array $columnSpan = 'full';

// Half width (2 columns)
protected int | string | array $columnSpan = 2;

// Responsive
protected int | string | array $columnSpan = [
    'sm' => 'full',
    'md' => 2,
    'lg' => 1,
];
```

### Conditional Display

```php
public static function canView(): bool
{
    return auth()->user()->can('view', TaxZone::class);
}
```

---

## Custom Widgets

Extend or replace widgets by registering your own:

```php
// AppServiceProvider
use AIArmada\FilamentTax\Widgets\TaxStatsWidget;
use Livewire\Livewire;

public function boot(): void
{
    // Replace the default widget
    Livewire::component('tax-stats-widget', App\Filament\Widgets\CustomTaxStatsWidget::class);
}
```

Or extend the existing widget:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentTax\Widgets\TaxStatsWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomTaxStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return array_merge(parent::getStats(), [
            Stat::make('Revenue Collected', '$45,230')
                ->description('This month')
                ->icon('heroicon-o-currency-dollar'),
        ]);
    }
}
```
