---
title: Filament Cashier CHIP Overview
---

# Filament Cashier CHIP

Filament admin panel integration for Cashier CHIP - subscription billing with the CHIP payment gateway.

## Features

- **Subscription Management** – Full CRUD for local CHIP subscriptions
- **Customer Dashboard** – View and manage billable customers
- **Invoice Tracking** – Browse invoice history from CHIP purchases
- **Billing Portal** – Customer self-service for subscriptions and payment methods
- **Analytics Widgets** – MRR, churn rate, active subscribers, revenue charts
- **Multi-tenant Ready** – Owner-scoped queries via commerce-support

## Architecture

### Admin Resources

| Resource | Description |
|----------|-------------|
| `SubscriptionResource` | Manage all subscriptions with status tracking |
| `CustomerResource` | View billable models and their CHIP client info |
| `InvoiceResource` | Browse invoices from CHIP purchases |

### Billing Portal Pages

| Page | Description |
|------|-------------|
| `BillingDashboard` | Customer billing overview |
| `Subscriptions` | Manage active subscriptions |
| `PaymentMethods` | Add/remove payment methods |
| `Invoices` | Download invoice history |

### Dashboard Widgets

| Widget | Description |
|--------|-------------|
| `MRRWidget` | Monthly Recurring Revenue with trend |
| `ActiveSubscribersWidget` | Total active subscriber count |
| `ChurnRateWidget` | Monthly churn rate percentage |
| `TrialConversionsWidget` | Trial-to-paid conversion rate |
| `AttentionRequiredWidget` | Past due subscriptions count |
| `RevenueChartWidget` | Revenue trend over time |
| `SubscriptionDistributionWidget` | Subscriptions by plan |

## Quick Start

### Installation

```bash
composer require aiarmada/filament-cashier-chip
```

### Register Plugin

```php
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCashierChipPlugin::make(),
        ]);
}
```

### Publish Config

```bash
php artisan vendor:publish --tag=filament-cashier-chip-config
```

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament 5.x
- aiarmada/cashier-chip package

## Quick Links

| Guide | Description |
|-------|-------------|
| [Installation](02-installation.md) | Setup and configuration |
| [Configuration](03-configuration.md) | All config options |
| [Resources](04-resources.md) | Admin panel resources |
| [Billing Portal](05-billing-portal.md) | Customer self-service |
| [Widgets](06-widgets.md) | Dashboard analytics |

---

**Ready?** Start with [Installation](02-installation.md) →
