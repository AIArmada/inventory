---
title: Filament Cashier Overview
---

# Filament Cashier

Unified Filament admin interface for multi-gateway billing management with Stripe and CHIP support.

## Overview

`aiarmada/filament-cashier` provides a single pane of glass for managing billing across multiple payment gateways. Built on top of `aiarmada/cashier`, it offers:

- **Unified Subscription Management** - View and manage subscriptions from Stripe and CHIP in one resource
- **Multi-Gateway Dashboard** - Combined MRR, churn, and analytics across all gateways
- **Cross-Gateway Customer Portal** - Customers manage all billing in one location
- **Gateway-Agnostic Operations** - Perform actions without knowing which gateway handles each subscription

## Package Hierarchy

```
Payment Gateway APIs (Stripe, CHIP)
    │
    ├── laravel/cashier            ← Stripe billing
    │
    └── aiarmada/cashier-chip      ← CHIP billing
        │
        └── aiarmada/cashier       ← Unified multi-gateway wrapper
            │
            └── aiarmada/filament-cashier  ← THIS PACKAGE
```

## Features

### Admin Panel Features

| Feature | Description |
|---------|-------------|
| **Unified Subscriptions** | Single resource showing all subscriptions across gateways |
| **Unified Invoices** | Combined invoice history from all gateways |
| **Billing Dashboard** | Combined MRR, subscribers, churn metrics |
| **Gateway Management** | Health monitoring and default gateway configuration |

### Dashboard Widgets

| Widget | Description |
|--------|-------------|
| `TotalMrrWidget` | Combined Monthly Recurring Revenue |
| `TotalSubscribersWidget` | Active subscribers across gateways |
| `GatewayBreakdownWidget` | Revenue distribution by gateway |
| `GatewayComparisonWidget` | 6-month revenue comparison chart |
| `UnifiedChurnWidget` | Combined monthly churn metrics |

### Customer Portal Features

| Feature | Description |
|---------|-------------|
| **Billing Overview** | Dashboard with subscriptions, payment methods, invoices |
| **Manage Subscriptions** | View, cancel, resume subscriptions |
| **Payment Methods** | Manage payment methods per gateway |
| **View Invoices** | Download invoices from all gateways |

## Gateway Support

| Gateway | Package | Features |
|---------|---------|----------|
| Stripe | `laravel/cashier` | Full subscription management, invoices, payment methods |
| CHIP | `aiarmada/cashier-chip` | Malaysian payments, FPX, e-wallets, subscriptions |

The package automatically detects installed gateways and enables features accordingly.

## Requirements

- PHP 8.2+
- Laravel 12.0+
- Filament 5.0+
- `aiarmada/cashier` (required)
- At least one gateway package (optional but recommended)

## Architecture

### Key Classes

| Class | Purpose |
|-------|---------|
| `FilamentCashierPlugin` | Filament panel plugin |
| `GatewayDetector` | Detects available payment gateways |
| `UnifiedSubscription` | DTO normalizing subscription data |
| `UnifiedInvoice` | DTO normalizing invoice data |
| `SubscriptionStatus` | Normalized status enum |
| `InvoiceStatus` | Normalized invoice status enum |
| `CashierOwnerScope` | Owner/tenant scoping for queries |

### Design Principles

1. **Abstraction Layer** - Never calls gateway APIs directly; delegates to `aiarmada/cashier`
2. **Gateway Independence** - Works with only one gateway installed
3. **Graceful Degradation** - Features disable if gateway not available
4. **Delegation Pattern** - Complex operations delegate to gateway-specific packages
5. **No Data Duplication** - Uses existing gateway tables via DTOs

## Quick Start

```php
// In your Filament PanelProvider
use AIArmada\FilamentCashier\FilamentCashierPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCashierPlugin::make()
                ->navigationGroup('Billing')
                ->dashboard()
                ->subscriptions()
                ->invoices(),
        ]);
}
```

## Related Packages

| Package | Purpose |
|---------|---------|
| `aiarmada/cashier` | Core multi-gateway billing abstraction |
| `aiarmada/cashier-chip` | CHIP payment gateway integration |
| `aiarmada/filament-cashier-chip` | Enhanced CHIP-specific Filament UI |
| `laravel/cashier` | Stripe integration (official Laravel package) |
