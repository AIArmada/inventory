---
title: Pages & Widgets
---

# Pages & Widgets

## Pages

### Aging Report Page

The Aging Report Page shows a breakdown of outstanding invoices by age buckets.

**Route:** `/admin/docs/aging-report` (registered via DocPlugin)

**Features:**
- Age buckets: Current, 1-30 days, 31-60 days, 61-90 days, 90+ days
- Summary statistics per bucket
- Total outstanding amount
- Drill-down to individual invoices
- Export to CSV

**Configuration:**

The page is enabled by default. To disable:

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->agingReportEnabled(false),
]);
```

**Extending:**

```php
<?php

namespace App\Filament\Pages;

use AIArmada\FilamentDocs\Pages\AgingReportPage as BaseAgingReportPage;

class AgingReportPage extends BaseAgingReportPage
{
    protected function getAgeBuckets(): array
    {
        return [
            'current' => ['label' => 'Current', 'min' => 0, 'max' => 0],
            '1-15' => ['label' => '1-15 Days', 'min' => 1, 'max' => 15],
            '16-30' => ['label' => '16-30 Days', 'min' => 16, 'max' => 30],
            '31-60' => ['label' => '31-60 Days', 'min' => 31, 'max' => 60],
            '61-90' => ['label' => '61-90 Days', 'min' => 61, 'max' => 90],
            '90+' => ['label' => '90+ Days', 'min' => 91, 'max' => PHP_INT_MAX],
        ];
    }
}
```

---

### Pending Approvals Page

Displays documents awaiting the current user's approval.

**Route:** `/admin/docs/pending-approvals`

**Features:**
- List of documents pending approval
- Approve/Reject actions with comments
- Bulk approve/reject
- Filter by document type
- Sort by due date or priority

**Configuration:**

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->pendingApprovalsEnabled(true), // default: true
]);
```

---

## Widgets

All widgets are designed to work with multi-tenancy via HasOwner scoping.

### DocStatsWidget

Shows key document statistics in stat cards.

**Stats Displayed:**
- Total Documents (this month)
- Pending Documents
- Overdue Documents
- Paid Documents
- Revenue (sum of paid invoices)

**Configuration:**

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->docStatsWidgetEnabled(true),
]);
```

**Extending:**

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\FilamentDocs\Widgets\DocStatsWidget as BaseDocStatsWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocStatsWidget extends BaseDocStatsWidget
{
    protected function getStats(): array
    {
        return array_merge(parent::getStats(), [
            Stat::make('Drafted', $this->getDraftedCount())
                ->icon('heroicon-o-pencil'),
        ]);
    }
    
    protected function getDraftedCount(): int
    {
        return Doc::forOwner(OwnerContext::owner())
            ->where('status', DocStatus::Draft)
            ->count();
    }
}
```

---

### QuickActionsWidget

Provides quick action buttons for common tasks.

**Actions:**
- Create Invoice
- Create Quotation
- Create Receipt
- View Aging Report
- Export Documents

**Configuration:**

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->quickActionsWidgetEnabled(true),
]);
```

---

### RecentDocumentsWidget

Displays a table of recently created or updated documents.

**Columns:**
- Document number (linked to view page)
- Type badge
- Customer name
- Total amount
- Status badge
- Created at

**Configuration:**

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->recentDocumentsWidgetEnabled(true),
]);
```

**Extending:**

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget as BaseWidget;
use Filament\Tables\Table;

class RecentDocumentsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 3;
    
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordsPerPage(10)
            ->defaultSort('created_at', 'desc');
    }
}
```

---

### RevenueChartWidget

Displays a chart of revenue over time.

**Chart Type:** Area or Line chart

**Data:**
- Revenue by month (paid invoices)
- Comparison with previous period
- Currency-formatted totals

**Configuration:**

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->revenueChartWidgetEnabled(true),
]);
```

---

### StatusBreakdownWidget

Shows a pie/doughnut chart of document status distribution.

**Segments:**
- Draft (gray)
- Pending (amber)
- Sent (blue)
- Paid (green)
- Overdue (red)
- Cancelled (dark gray)

**Configuration:**

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

$panel->plugins([
    FilamentDocsPlugin::make()
        ->statusBreakdownWidgetEnabled(true),
]);
```

---

## Widget Placement

### Dashboard Widgets

To add widgets to your dashboard:

```php
<?php

namespace App\Providers;

use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->widgets([
                DocStatsWidget::class,
                RevenueChartWidget::class,
                RecentDocumentsWidget::class,
            ]);
    }
}
```

### Resource Page Widgets

Widgets can also be added to resource pages:

```php
<?php

namespace App\Filament\Resources\DocResource\Pages;

use AIArmada\FilamentDocs\Resources\DocResource\Pages\ListDocs as BaseListDocs;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;

class ListDocs extends BaseListDocs
{
    protected function getHeaderWidgets(): array
    {
        return [
            DocStatsWidget::class,
        ];
    }
}
```

---

## Creating Custom Widgets

### Stats Widget Example

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Support\Traits\HasOwnerScopeConfig;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class CustomDocStatsWidget extends BaseWidget
{
    use HasOwnerScopeConfig;

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Average Invoice Value', $this->getAverageInvoiceValue())
                ->description('Last 30 days')
                ->icon('heroicon-o-banknotes'),
                
            Stat::make('Conversion Rate', $this->getConversionRate() . '%')
                ->description('Quotations to Invoices')
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }

    protected function getAverageInvoiceValue(): string
    {
        $average = $this->getBaseQuery()
            ->where('type', 'invoice')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total');
            
        return number_format($average ?? 0, 2);
    }

    protected function getConversionRate(): float
    {
        // Custom logic for conversion rate
        return 65.5;
    }

    protected function getBaseQuery(): Builder
    {
        return Doc::forOwner($this->getOwner());
    }
}
```

### Chart Widget Example

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\Docs\Models\Doc;
use AIArmada\Support\Traits\HasOwnerScopeConfig;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MonthlyRevenueChart extends ChartWidget
{
    use HasOwnerScopeConfig;

    protected static ?string $heading = 'Monthly Revenue';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = Doc::forOwner($this->getOwner())
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('total')->toArray(),
                    'fill' => true,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```
