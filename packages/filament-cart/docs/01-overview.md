---
title: Overview
---

# Filament Cart

A comprehensive Filament admin panel plugin for managing shopping carts in Laravel e-commerce applications. This package provides a complete administrative interface for the `aiarmada/cart` package, offering real-time cart monitoring, analytics dashboards, abandoned cart recovery, fraud detection, and alerting capabilities.

## Features

### Core Cart Management
- **Cart Resource** — View and manage cart snapshots with items, conditions, and totals
- **Cart Item Resource** — Analyze individual line items across all carts
- **Cart Condition Resource** — Monitor applied discounts, taxes, fees, and shipping conditions
- **Condition Resource** — Create and manage reusable condition templates

### Analytics & Dashboards
- **Cart Dashboard** — Overview of cart activity, abandonment rates, and fraud alerts
- **Analytics Page** — Comprehensive reporting with conversion funnels, trends, and exports
- **Live Monitor** — Real-time cart activity tracking with automatic polling

### Cart Recovery System
- **Recovery Campaigns** — Automated cart abandonment recovery workflows
- **Recovery Templates** — Email, SMS, and push notification templates
- **A/B Testing** — Built-in split testing for recovery campaigns
- **Performance Tracking** — Open rates, click rates, and conversion analytics

### Monitoring & Alerts
- **Alert Rules** — Configurable rules for cart events (abandonment, fraud, high-value)
- **Multi-channel Notifications** — Email, Slack, webhook, and in-app alerts
- **Fraud Detection** — Automated detection of suspicious cart patterns

### Multitenancy Support
- Full owner-scoping for multi-tenant applications
- Configurable global row inclusion
- Automatic owner context resolution

## Package Architecture

```
filament-cart/
├── config/filament-cart.php     # Package configuration
├── database/migrations/         # Database schema
├── src/
│   ├── FilamentCartPlugin.php   # Filament plugin registration
│   ├── FilamentCartServiceProvider.php
│   ├── Actions/                 # Filament table/form actions
│   ├── Commands/                # Artisan commands
│   ├── Data/                    # DTOs (spatie/laravel-data)
│   ├── Events/                  # Package events
│   ├── Jobs/                    # Queue jobs
│   ├── Listeners/               # Event listeners
│   ├── Models/                  # Eloquent models
│   ├── Pages/                   # Filament pages
│   ├── Resources/               # Filament resources
│   ├── Services/                # Business logic services
│   └── Widgets/                 # Dashboard widgets
└── resources/views/             # Blade views
```

## Database Tables

The package creates the following tables (configurable via table prefix):

| Table | Description |
|-------|-------------|
| `cart_snapshots` | Normalized cart state snapshots |
| `cart_snapshot_items` | Cart line items |
| `cart_snapshot_conditions` | Applied conditions |
| `cart_daily_metrics` | Aggregated daily analytics |
| `cart_recovery_campaigns` | Recovery campaign configurations |
| `cart_recovery_templates` | Message templates |
| `cart_recovery_attempts` | Individual recovery attempts |
| `cart_alert_rules` | Alert rule definitions |
| `cart_alert_logs` | Alert history |

## Key Services

| Service | Purpose |
|---------|---------|
| `NormalizedCartSynchronizer` | Syncs cart state to normalized database models |
| `CartSyncManager` | Manages cart synchronization lifecycle |
| `CartAnalyticsService` | Computes dashboard metrics and reports |
| `CartMonitor` | Real-time cart monitoring |
| `RecoveryScheduler` | Schedules abandoned cart recovery attempts |
| `RecoveryDispatcher` | Dispatches recovery messages |
| `AlertEvaluator` | Evaluates alert rule conditions |
| `AlertDispatcher` | Sends alerts to configured channels |

## Integration with Cart Package

This package integrates seamlessly with `aiarmada/cart`:

1. **Event Synchronization** — Listens to cart events and maintains normalized database state
2. **Global Conditions** — Automatically applies global conditions from Condition model
3. **Condition Templates** — Uses `AIArmada\Cart\Models\Condition` for reusable templates
4. **Cart Resolution** — Resolves live cart instances from snapshots

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5+
- aiarmada/cart package
- aiarmada/commerce-support package

## Quick Start

```bash
# Install the package
composer require aiarmada/filament-cart

# Run migrations
php artisan migrate
```

Register the plugin in your Filament panel:

```php
use AIArmada\FilamentCart\FilamentCartPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCartPlugin::make(),
        ]);
}
```

## Navigation Structure

By default, resources appear under the "E-Commerce" navigation group:

- **Carts** — Cart snapshots (badge shows count)
- **Cart Items** — Individual line items
- **Cart Conditions** — Applied conditions
- **Conditions** — Reusable templates (badge shows active count)
- **Recovery Campaigns** — Abandonment recovery
- **Recovery Templates** — Message templates
- **Alert Rules** — Monitoring rules

Pages:
- **Cart Analytics** — Main dashboard
- **Analytics Report** — Detailed reporting
- **Live Monitor** — Real-time monitoring
- **Recovery Settings** — Configuration page
