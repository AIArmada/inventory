---
title: Resources
---

# Resources

All resources extend `BaseChipResource` which provides owner scoping, consistent navigation, and shared table/form components.

## Registered Resources

These resources are registered automatically:

| Resource | Model | Description |
|----------|-------|-------------|
| `PurchaseResource` | `Purchase` | Payment transactions |
| `ClientResource` | `Client` | Customer records |

## Optional Resources

These resources exist but are not registered by default:

| Resource | Model | Description |
|----------|-------|-------------|
| `BankAccountResource` | `BankAccount` | Payout bank accounts |
| `PaymentResource` | `Payment` | Payment records |
| `SendInstructionResource` | `SendInstruction` | Payout instructions |
| `CompanyStatementResource` | `CompanyStatement` | Company statements |

### Registering Optional Resources

```php
// In your PanelProvider
use AIArmada\FilamentChip\Resources\BankAccountResource;
use AIArmada\FilamentChip\Resources\SendInstructionResource;

$panel->resources([
    BankAccountResource::class,
    SendInstructionResource::class,
]);
```

## PurchaseResource

The primary resource for viewing payment transactions.

### Table Columns

| Column | Description |
|--------|-------------|
| External ID | CHIP reference |
| Amount | Transaction amount |
| Currency | Currency code |
| Status | Payment status |
| Email | Customer email |
| Created | Creation timestamp |
| Paid At | Payment timestamp |

### Filters

- **Status** - Filter by purchase status (pending, paid, cancelled, refunded)
- **Date Range** - Filter by creation date
- **Currency** - Filter by currency code

### Actions

- **View** - Modal with full purchase details
- **Refund** - Full or partial refund (for paid purchases)
- **Cancel** - Cancel pending purchases

## ClientResource

Customer records synchronized from CHIP.

### Features

- View client details
- List client's purchases
- Delete client (with confirmation)

## BankAccountResource

Manage payout recipient bank accounts (optional).

### Status Badges

- `pending` - Yellow, awaiting verification
- `verified` - Green, ready for payouts
- `rejected` - Red, verification failed

## SendInstructionResource

Manage disbursements and payouts (optional).

### Status States

| Status | Description |
|--------|-------------|
| received | Received |
| enquiring | Verifying |
| executing | Processing |
| reviewing | In Review |
| accepted | Accepted |
| completed | Completed |
| rejected | Rejected |

## Extending Resources

### Override Table

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentChip\Resources\PurchaseResource as BasePurchaseResource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PurchaseResource extends BasePurchaseResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::getTableColumns(),
                TextColumn::make('custom_field'),
            ]);
    }
}
```

## Owner Scoping

All resources respect owner scoping from `commerce-support`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->forCurrentOwner();
}
```
