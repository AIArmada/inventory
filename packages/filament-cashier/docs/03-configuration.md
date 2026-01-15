---
title: Configuration
---

# Configuration

Complete reference for all Filament Cashier configuration options.

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-cashier-config
```

This creates `config/filament-cashier.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Billing',
        'sort' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'polling_interval' => '45s',
        'date_format' => 'M d, Y',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'stripe' => [
            'label' => 'Stripe',
            'icon' => 'heroicon-o-credit-card',
            'color' => 'indigo',
            'dashboard_url' => 'https://dashboard.stripe.com',
        ],
        'chip' => [
            'label' => 'CHIP',
            'icon' => 'heroicon-o-cube',
            'color' => 'emerald',
            'dashboard_url' => 'https://gate.chip-in.asia',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'dashboard' => true,
        'subscriptions' => true,
        'invoices' => true,
        'gateway_management' => false,
        'customer_portal' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'subscriptions' => 10,
            'invoices' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Conversion
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'base' => 'USD',
        'display_converted' => false,
        'conversion_rates' => [
            'MYR' => 4.70,
            'USD' => 1.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Portal (Billing Panel)
    |--------------------------------------------------------------------------
    */
    'billing_portal' => [
        'enabled' => false,
        'panel_id' => 'billing',
        'path' => 'billing',
        'brand_name' => 'Billing Portal',
        'primary_color' => '#6366f1',
        'auth_guard' => 'web',
        'features' => [
            'subscriptions' => true,
            'payment_methods' => true,
            'invoices' => true,
            'gateway_switching' => false,
        ],
    ],
];
```

## Navigation Configuration

### Via Config

```php
'navigation' => [
    'group' => 'Billing',  // Navigation group name
    'sort' => 50,          // Sort order within panel
],
```

### Via Plugin

```php
FilamentCashierPlugin::make()
    ->navigationGroup('Finance')
    ->navigationSort(10)
```

Plugin options override config values.

## Gateway Configuration

Each gateway can be customized with label, icon, color, and dashboard URL:

```php
'gateways' => [
    'stripe' => [
        'label' => 'Stripe',                              // Display name
        'icon' => 'heroicon-o-credit-card',               // Heroicon name
        'color' => 'indigo',                              // Filament color
        'dashboard_url' => 'https://dashboard.stripe.com', // External dashboard
    ],
    'chip' => [
        'label' => 'CHIP Malaysia',
        'icon' => 'heroicon-o-cube',
        'color' => 'emerald',
        'dashboard_url' => 'https://gate.chip-in.asia',
    ],
],
```

### Supported Colors

- `primary`, `success`, `warning`, `danger`, `info`
- `gray`, `indigo`, `emerald`, `amber`, `violet`

## Feature Toggles

Enable or disable features via config or plugin:

### Via Config

```php
'features' => [
    'dashboard' => true,            // Billing dashboard page
    'subscriptions' => true,        // Subscriptions resource
    'invoices' => true,             // Invoices resource
    'gateway_management' => false,  // Gateway management page
    'customer_portal' => false,     // Customer-facing portal mode
],
```

### Via Plugin

```php
FilamentCashierPlugin::make()
    ->dashboard(true)
    ->subscriptions(true)
    ->invoices(true)
    ->gatewayManagement(true)
    ->customerPortalMode(false)
```

## Currency Configuration

For multi-currency setups:

```php
'currency' => [
    'base' => 'USD',                // Base currency for totals
    'display_converted' => false,   // Convert all amounts to base currency
    'conversion_rates' => [
        'MYR' => 4.70,
        'USD' => 1.00,
        'EUR' => 0.92,
    ],
],
```

When `display_converted` is `true`, all MRR and revenue figures are converted to the base currency.

## Table Configuration

```php
'tables' => [
    'polling_interval' => '45s',    // Auto-refresh interval
    'date_format' => 'M d, Y',      // Date display format
],
```

### Polling Interval Options

- `'10s'` - Every 10 seconds (high frequency)
- `'30s'` - Every 30 seconds
- `'45s'` - Every 45 seconds (default)
- `'60s'` - Every minute
- `null` - Disable auto-refresh

## Resource Sort Order

Control navigation sort order for resources:

```php
'resources' => [
    'navigation_sort' => [
        'subscriptions' => 10,  // Lower = higher in nav
        'invoices' => 20,
    ],
],
```

## Customer Portal Configuration

Configure the customer-facing billing portal:

```php
'billing_portal' => [
    'enabled' => true,                    // Enable the portal
    'panel_id' => 'billing',              // Filament panel ID
    'path' => 'billing',                  // URL path (/billing)
    'brand_name' => 'My App Billing',     // Brand name
    'primary_color' => '#6366f1',         // Primary color (hex)
    'auth_guard' => 'web',                // Auth guard to use
    'login_enabled' => true,              // Show login page
    'features' => [
        'subscriptions' => true,          // Show subscriptions
        'payment_methods' => true,        // Show payment methods
        'invoices' => true,               // Show invoices
        'gateway_switching' => false,     // Allow gateway switching
    ],
],
```

### Primary Color Options

| Hex | Color |
|-----|-------|
| `#6366f1` | Indigo (default) |
| `#3b82f6` | Blue |
| `#10b981` | Emerald |
| `#f59e0b` | Amber |
| `#ef4444` | Red |
| `#8b5cf6` | Violet |

## Authorization & Policies

The package includes built-in policies for customer portal authorization:

### SubscriptionPolicy

```php
namespace AIArmada\FilamentCashier\Policies;

class SubscriptionPolicy
{
    public function view(Model $user, Model $subscription): bool;
    public function cancel(Model $user, Model $subscription): bool;
    public function resume(Model $user, Model $subscription): bool;
    public function update(Model $user, Model $subscription): bool;
    public function swap(Model $user, Model $subscription): bool;
}
```

### PaymentMethodPolicy

```php
namespace AIArmada\FilamentCashier\Policies;

class PaymentMethodPolicy
{
    public function view(Model $user, Model $paymentMethod): bool;
    public function create(Model $user): bool;
    public function update(Model $user, Model $paymentMethod): bool;
    public function delete(Model $user, Model $paymentMethod): bool;
    public function setDefault(Model $user, Model $paymentMethod): bool;
}
```

Both policies ensure users can only manage their own resources.

## Multitenancy

For multi-tenant applications, the package uses `CashierOwnerScope` to enforce tenant boundaries.

If your billable model supports `scopeForOwner()`, all queries will be automatically scoped to the current owner context.

```php
// Your User model
public function scopeForOwner(Builder $query, Model $owner): Builder
{
    return $query->where('team_id', $owner->getKey());
}
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `FILAMENT_CASHIER_BRAND_NAME` | Portal brand name | `Billing Portal` |
| `FILAMENT_CASHIER_PRIMARY_COLOR` | Portal primary color | `#6366f1` |
