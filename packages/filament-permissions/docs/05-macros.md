# Macros & Authorization

This guide covers using permission macros to protect Filament components.

## Overview

Filament Permissions provides convenience macros for common authorization patterns. These macros hide components from unauthorized users and prevent action execution.

## Action Macros

### requiresPermission

Hide and disable an action based on permission:

```php
use Filament\Actions\Action;

Action::make('export')
    ->requiresPermission('order.export')
    ->action(fn () => $this->exportOrders());
```

The action:
- Is hidden if user lacks permission
- Is not authorized if user lacks permission

### requiresRole

Restrict action to specific roles:

```php
Action::make('refund')
    ->requiresRole('Admin')
    ->action(fn () => $this->processRefund());

// Multiple roles (any match)
Action::make('void')
    ->requiresRole(['Admin', 'Finance'])
    ->action(fn () => $this->voidTransaction());
```

## Table Column Macros

### requiresPermission

Conditionally show columns:

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('cost_price')
    ->requiresPermission('product.view_costs')
    ->money('USD');

TextColumn::make('internal_notes')
    ->requiresPermission('order.view_internal');
```

### requiresRole

Role-based column visibility:

```php
TextColumn::make('profit_margin')
    ->requiresRole('Finance')
    ->suffix('%');

TextColumn::make('supplier_contact')
    ->requiresRole(['Admin', 'Purchasing']);
```

## Table Filter Macros

### requiresPermission

Restrict filter access:

```php
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

Filter::make('high_value')
    ->requiresPermission('order.filter_advanced')
    ->query(fn ($query) => $query->where('total', '>', 10000));

SelectFilter::make('assigned_to')
    ->requiresPermission('order.view_assignments')
    ->relationship('assignee', 'name');
```

### requiresRole

Role-based filter access:

```php
Filter::make('flagged')
    ->requiresRole('Compliance')
    ->query(fn ($query) => $query->where('flagged', true));
```

## Navigation Item Macros

### requiresPermission

Conditional navigation visibility:

```php
use Filament\Navigation\NavigationItem;

NavigationItem::make('Reports')
    ->url('/admin/reports')
    ->requiresPermission('report.viewAny')
    ->icon('heroicon-o-chart-bar');
```

### requiresRole

Role-based navigation:

```php
NavigationItem::make('System Settings')
    ->url('/admin/settings')
    ->requiresRole('Super Admin')
    ->icon('heroicon-o-cog');

NavigationItem::make('Analytics')
    ->url('/admin/analytics')
    ->requiresRole(['Admin', 'Analyst'])
    ->icon('heroicon-o-chart-pie');
```

## Resource Authorization

### Navigation Visibility

Resources use `shouldRegisterNavigation()`:

```php
public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();

    return $user?->can('order.viewAny') 
        || $user?->hasRole(config('filament-permissions.super_admin_role'));
}
```

### Table Actions

Authorize individual actions:

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([...])
        ->actions([
            EditAction::make()
                ->authorize(fn ($record) => auth()->user()?->can('order.update')),
            DeleteAction::make()
                ->authorize(fn ($record) => auth()->user()?->can('order.delete')),
        ]);
}
```

### Bulk Actions

```php
->bulkActions([
    DeleteBulkAction::make()
        ->authorize(fn () => auth()->user()?->can('order.delete')),
    ExportBulkAction::make()
        ->requiresPermission('order.export'),
])
```

## Widget Authorization

### canView Method

Widgets override `canView()`:

```php
use Filament\Widgets\StatsOverviewWidget;

class RevenueWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->can('dashboard.view_revenue') 
            || $user?->hasRole('Finance');
    }
}
```

## Form Field Authorization

While no built-in macro exists, use conditional logic:

```php
use Filament\Forms\Components\TextInput;

TextInput::make('discount_override')
    ->visible(fn () => auth()->user()?->can('order.override_discount'))
    ->numeric()
    ->suffix('%');
```

## Combining Conditions

Macros can be chained with other methods:

```php
Action::make('archive')
    ->requiresPermission('order.archive')
    ->visible(fn ($record) => $record->status === 'completed')
    ->color('warning')
    ->icon('heroicon-o-archive-box');
```

## Super Admin Consideration

Super Admin users bypass permission checks via `Gate::before()`. Macros using `->can()` automatically respect this:

```php
// Super Admin sees this even without explicit 'secret.action' permission
Action::make('secret')
    ->requiresPermission('secret.action');
```

## Custom Authorization Logic

For complex authorization, use closures:

```php
Action::make('special')
    ->visible(function () {
        $user = auth()->user();
        
        return $user?->can('order.special') 
            && $user?->team_id === 1
            && now()->isWeekday();
    })
    ->authorize(function () {
        // Same logic for execution authorization
    });
```

## Best Practices

1. **Consistent naming** — Use `{model}.{ability}` format
2. **Fail closed** — Default to hidden/unauthorized
3. **Cache awareness** — Clear permission cache after changes
4. **Super Admin** — Don't hardcode Super Admin checks; rely on Gate::before
5. **Test coverage** — Test both authorized and unauthorized scenarios
