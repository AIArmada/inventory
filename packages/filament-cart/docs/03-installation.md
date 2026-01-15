---
title: Installation
---

# Installation

This guide covers installing and configuring the Filament Cart package in your Laravel application.

## Requirements

- **PHP** 8.4+
- **Laravel** 12+
- **Filament** 5+
- **aiarmada/cart** package (core cart functionality)
- **aiarmada/commerce-support** package (shared utilities)

## Install via Composer

```bash
composer require aiarmada/filament-cart
```

This will also install the required dependencies (`aiarmada/cart` and `aiarmada/commerce-support`).

## Run Migrations

The package includes migrations for normalized cart storage and additional features:

```bash
php artisan migrate
```

### Database Tables Created

| Table | Purpose |
|-------|---------|
| `cart_snapshots` | Normalized cart state snapshots |
| `cart_snapshot_items` | Individual cart line items |
| `cart_snapshot_conditions` | Applied conditions |
| `cart_daily_metrics` | Aggregated daily analytics |
| `cart_recovery_campaigns` | Recovery campaign configurations |
| `cart_recovery_templates` | Message templates for recovery |
| `cart_recovery_attempts` | Individual recovery attempts |
| `cart_alert_rules` | Alert rule definitions |
| `cart_alert_logs` | Alert history and logs |

## Register the Plugin

Add `FilamentCartPlugin` to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentCart\FilamentCartPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentCartPlugin::make(),
            ]);
    }
}
```

## Publish Configuration

Optionally publish the configuration file for customization:

```bash
php artisan vendor:publish --tag="filament-cart-config"
```

This creates `config/filament-cart.php` where you can customize:

- Database table names and prefix
- Navigation group and sort order
- Feature toggles
- Polling intervals
- Owner scoping settings
- AI/Analytics thresholds
- Alert channel configuration

## Publish Views (Optional)

To customize the package views:

```bash
php artisan vendor:publish --tag="filament-cart-views"
```

Views are published to `resources/views/vendor/filament-cart/`.

## Verify Installation

After installation, navigate to your Filament admin panel. You should see the **E-Commerce** navigation group (or your configured group) with:

**Resources:**
- Carts
- Cart Items
- Cart Conditions
- Conditions
- Recovery Campaigns (if enabled)
- Recovery Templates (if enabled)
- Alert Rules (if enabled)

**Pages:**
- Cart Analytics (dashboard)
- Analytics Report
- Live Monitor (if enabled)
- Recovery Settings (if enabled)

**Widgets:**
- Cart Stats Widget (on dashboard)

## Artisan Commands

The package provides several artisan commands:

```bash
# Aggregate daily metrics for analytics
php artisan cart:aggregate-metrics

# Schedule recovery campaigns
php artisan cart:schedule-recovery

# Process scheduled recovery attempts
php artisan cart:process-recovery

# Monitor carts for alerts
php artisan cart:monitor

# Process pending alerts
php artisan cart:process-alerts
```

### Scheduling Commands

Add these to your `app/Console/Kernel.php` or `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Aggregate metrics daily
Schedule::command('cart:aggregate-metrics')->dailyAt('00:05');

// Check for abandonment and schedule recovery (every 15 minutes)
Schedule::command('cart:schedule-recovery')->everyFifteenMinutes();

// Process scheduled recovery attempts (every 5 minutes)
Schedule::command('cart:process-recovery')->everyFiveMinutes();

// Monitor carts and trigger alerts (every minute)
Schedule::command('cart:monitor')->everyMinute();

// Process queued alerts (every minute)
Schedule::command('cart:process-alerts')->everyMinute();
```

## Queue Configuration

For better performance, especially in high-traffic applications, configure queue workers for cart operations:

```php
// config/filament-cart.php
'synchronization' => [
    'queue_sync' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'cart-sync',
],
```

Then ensure your queue worker processes the cart-sync queue:

```bash
php artisan queue:work redis --queue=cart-sync
```

## Environment Variables

Common environment variables you may want to set:

```env
# JSON column type (json or jsonb for PostgreSQL)
FILAMENT_CART_JSON_COLUMN_TYPE=json

# Enable owner scoping for multitenancy
FILAMENT_CART_OWNER_ENABLED=false
FILAMENT_CART_OWNER_INCLUDE_GLOBAL=false

# Slack webhook for alerts
CART_SLACK_WEBHOOK=https://hooks.slack.com/services/...
```

## Next Steps

- [Configuration](04-configuration.md) — Complete configuration reference
- [Resources](02-resources.md) — Learn about available Filament resources
- [Synchronization](05-synchronization.md) — Understand event-driven sync
- [Analytics](07-analytics.md) — Set up analytics and reporting
- [Recovery](08-recovery.md) — Configure cart recovery campaigns
- [Monitoring](09-monitoring.md) — Set up alerts and monitoring
