---
title: Admin Resources
---

# Admin Resources

Filament Cashier CHIP provides three main resources for managing billing data.

## SubscriptionResource

Manage all subscriptions with full status tracking.

### Features

- List all subscriptions with status badges
- View subscription details and items
- Filter by status, type, billing interval
- Search by type, CHIP ID, price
- Global search enabled

### Table Columns

| Column | Description |
|--------|-------------|
| Type | Subscription type name |
| Customer | Owner/billable name |
| Status | Color-coded status badge |
| Price | Subscription price ID |
| Interval | Billing frequency |
| Trial Ends | Trial period end date |
| Next Billing | Next charge date |
| Created | Creation timestamp |

### Status Colors

| Status | Color | Description |
|--------|-------|-------------|
| Active | Success (green) | Subscription is active |
| Trialing | Info (blue) | In trial period |
| Past Due | Warning (amber) | Payment failed |
| Canceled | Danger (red) | Canceled but in grace period |
| Incomplete | Gray | Initial payment pending |
| Paused | Gray | Temporarily paused |

### Infolist Sections

The view page shows:

1. **Subscription Details** – Type, status, price, quantity
2. **Billing Information** – Interval, next billing date, recurring token
3. **Trial & Cancellation** – Trial end, grace period, cancellation dates
4. **Timestamps** – Created and updated dates

### Relation Manager

The `SubscriptionItemsRelationManager` displays subscription line items:

| Column | Description |
|--------|-------------|
| Price | Price identifier |
| Product | Product identifier |
| Quantity | Item quantity |
| Unit Amount | Price per unit |

### Customizing the Resource

Extend the resource for customization:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentCashierChip\Resources\SubscriptionResource as BaseResource;

class SubscriptionResource extends BaseResource
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    
    public static function getNavigationLabel(): string
    {
        return 'My Subscriptions';
    }
}
```

## CustomerResource

View billable models and their CHIP client information.

### Features

- List all customers with CHIP IDs
- View customer billing details
- See associated subscriptions
- Filter by CHIP customer status

### Table Columns

| Column | Description |
|--------|-------------|
| Name | Customer name |
| Email | Customer email |
| CHIP ID | CHIP client ID |
| Subscriptions | Count of active subscriptions |
| Created | Account creation date |

### Infolist Sections

1. **Customer Information** – Name, email, phone
2. **CHIP Details** – Client ID, default payment method
3. **Subscriptions** – List of all subscriptions

### Customizing the Resource

```php
namespace App\Filament\Resources;

use AIArmada\FilamentCashierChip\Resources\CustomerResource as BaseResource;

class CustomerResource extends BaseResource
{
    public static function getModel(): string
    {
        // Use your custom billable model
        return \App\Models\Team::class;
    }
}
```

## InvoiceResource

Browse invoices from CHIP purchases.

### Features

- List all invoices/purchases
- View invoice details
- Filter by status
- Download invoice PDFs (if renderer configured)

### Table Columns

| Column | Description |
|--------|-------------|
| Number | Invoice/purchase ID |
| Customer | Customer name |
| Amount | Total amount |
| Status | Payment status |
| Date | Invoice date |

### Invoice Statuses

| Status | Color | Description |
|--------|-------|-------------|
| Paid | Success | Payment completed |
| Open | Warning | Awaiting payment |
| Voided | Gray | Invoice canceled |
| Draft | Gray | Not yet finalized |

## Owner Scoping

All resources automatically apply owner scoping when enabled:

```php
// In BaseCashierChipResource
public static function getEloquentQuery(): Builder
{
    return CashierChipOwnerScope::apply(parent::getEloquentQuery());
}
```

This ensures:
- Each tenant only sees their own data
- Cross-tenant data is never exposed
- Works with Filament's tenant features

### Disabling Owner Scoping

For super-admin panels that need global access:

```php
// config/cashier-chip.php
'features' => [
    'owner' => [
        'enabled' => false, // Disable for this panel
    ],
],
```

## Resource Actions

### Subscription Actions

Currently view-only. To add custom actions:

```php
use Filament\Tables\Actions\Action;

public static function getTableActions(): array
{
    return [
        Action::make('cancel')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn ($record) => $record->cancel()),
            
        Action::make('resume')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn ($record) => $record->onGracePeriod())
            ->action(fn ($record) => $record->resume()),
    ];
}
```

## Global Search

Subscriptions are globally searchable by:
- Type name
- CHIP ID
- Price identifier

Enable in your panel:

```php
$panel->globalSearch(true);
```

## Navigation Badges

Each resource shows a count badge:

```php
// Shows count of records
public static function getNavigationBadge(): ?string
{
    $count = static::getEloquentQuery()->count();
    return $count > 0 ? (string) $count : null;
}
```

## Next Steps

- [Billing Portal](05-billing-portal.md) – Customer self-service
- [Widgets](06-widgets.md) – Dashboard analytics
