# Packages

Overview of all packages in the AIArmada Commerce suite.

## Core Packages

### commerce-support

Foundation package with shared utilities.

| Feature | Description |
|---------|-------------|
| Exceptions | Standardized exception hierarchy |
| Contracts | Payment gateway interfaces |
| Helpers | JSON column type resolver |
| Traits | Configuration validation |

```bash
composer require aiarmada/commerce-support
```

### cart

Shopping cart with conditions and persistence.

| Feature | Description |
|---------|-------------|
| Items | Add, update, remove cart items |
| Conditions | Discounts, taxes, shipping fees |
| Persistence | Session, database, or custom storage |
| Events | Cart lifecycle events |

```bash
composer require aiarmada/cart
```

### stock

Inventory and stock management.

| Feature | Description |
|---------|-------------|
| Stock Levels | Track quantity per location |
| Reservations | Hold stock during checkout |
| Movements | Track stock in/out |
| Low Stock | Alerts and notifications |

```bash
composer require aiarmada/stock
```

### vouchers

Discount codes and promotional vouchers.

| Feature | Description |
|---------|-------------|
| Voucher Types | Percentage, fixed, free shipping |
| Conditions | Min order, date range, usage limits |
| Validation | Real-time code validation |
| Tracking | Usage history and analytics |

```bash
composer require aiarmada/vouchers
```

### docs

Invoice and receipt generation with PDF.

| Feature | Description |
|---------|-------------|
| Documents | Invoices, receipts, quotes |
| Templates | Customizable Blade templates |
| PDF | Generate and store PDFs |
| Numbering | Auto-incrementing doc numbers |

```bash
composer require aiarmada/docs
```

## Payment & Shipping

### chip

CHIP payment gateway integration.

| Feature | Description |
|---------|-------------|
| Payments | Create, retrieve, cancel |
| Refunds | Full and partial refunds |
| Webhooks | Payment status callbacks |
| FPX/Cards | Malaysian payment methods |

```bash
composer require aiarmada/chip
```

### cashier

Payment orchestration layer.

| Feature | Description |
|---------|-------------|
| Gateway Abstraction | Unified payment interface |
| Multi-Gateway | Support multiple providers |
| Checkout | Streamlined checkout flow |

```bash
composer require aiarmada/cashier
```

### cashier-chip

CHIP adapter for Cashier.

```bash
composer require aiarmada/cashier-chip
```

### jnt

J&T Express shipping integration.

| Feature | Description |
|---------|-------------|
| Rates | Get shipping rates |
| Orders | Create shipping orders |
| Tracking | Track shipments |
| Webhooks | Status updates |

```bash
composer require aiarmada/jnt
```

## Filament Admin Panels

### filament-cart

Cart management admin panel.

| Feature | Description |
|---------|-------------|
| Cart List | View all carts |
| Cart Details | Items, conditions, totals |
| Conditions | Manage global conditions |

```bash
composer require aiarmada/filament-cart
```

### filament-stock

Stock management admin panel.

| Feature | Description |
|---------|-------------|
| Stock Levels | View/edit stock |
| Movements | Stock history |
| Locations | Multi-location support |

```bash
composer require aiarmada/filament-stock
```

### filament-vouchers

Voucher management admin panel.

| Feature | Description |
|---------|-------------|
| Voucher CRUD | Create, edit, delete |
| Usage Stats | Track redemptions |
| Bulk Actions | Generate codes |

```bash
composer require aiarmada/filament-vouchers
```

### filament-docs

Document management admin panel.

| Feature | Description |
|---------|-------------|
| Documents | Create invoices, receipts |
| Templates | Manage doc templates |
| PDF | Generate and download |
| Status | Track payment status |

```bash
composer require aiarmada/filament-docs
```

### filament-chip

CHIP payment admin panel.

| Feature | Description |
|---------|-------------|
| Payments | View payment history |
| Refunds | Process refunds |
| Webhooks | View webhook logs |

```bash
composer require aiarmada/filament-chip
```

### filament-jnt

J&T Express admin panel.

| Feature | Description |
|---------|-------------|
| Orders | View shipping orders |
| Tracking | Track shipments |
| Rates | Rate calculator |

```bash
composer require aiarmada/filament-jnt
```

### filament-authz

Role & permission management.

| Feature | Description |
|---------|-------------|
| Roles | Create and assign roles |
| Permissions | Granular permissions |
| Users | User role assignment |

```bash
composer require aiarmada/filament-authz
```

## Package Selection Guide

### E-commerce Store

```bash
composer require aiarmada/cart aiarmada/vouchers aiarmada/stock aiarmada/chip
composer require aiarmada/filament-cart aiarmada/filament-vouchers aiarmada/filament-stock aiarmada/filament-chip
```

### Invoice System

```bash
composer require aiarmada/docs aiarmada/chip
composer require aiarmada/filament-docs aiarmada/filament-chip
```

### Shipping Only

```bash
composer require aiarmada/jnt
composer require aiarmada/filament-jnt
```

### Everything

```bash
composer require aiarmada/commerce
```
