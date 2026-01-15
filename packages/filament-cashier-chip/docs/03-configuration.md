---
title: Configuration
---

# Configuration

Complete reference for `config/filament-cashier-chip.php`.

## Full Configuration

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        // Navigation group name for all resources
        'group' => 'Billing',
        
        // Badge color for record counts
        'badge_color' => 'success',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        // Auto-refresh interval for table data
        'polling_interval' => '45s',
        
        // Date format for table columns
        'date_format' => 'Y-m-d H:i:s',
        
        // Decimal precision for monetary amounts
        'amount_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Enable subscription resource
        'subscriptions' => true,
        
        // Enable customer resource
        'customers' => true,
        
        // Enable invoice resource
        'invoices' => true,
        
        // Enable payment method management
        'payment_methods' => true,
        
        // Enable dashboard widgets
        'dashboard_widgets' => true,
        
        // Individual widget toggles
        'dashboard' => [
            'widgets' => [
                'mrr' => true,
                'active_subscribers' => true,
                'churn_rate' => true,
                'attention_required' => true,
                'revenue_chart' => true,
                'subscription_distribution' => true,
                'trial_conversions' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Portal
    |--------------------------------------------------------------------------
    */
    'billing' => [
        // Model that uses Billable trait (null = auto-detect)
        'billable_model' => null,
        
        // Filament panel ID for billing portal
        'panel_id' => 'billing',
        
        // URL path for billing portal
        'path' => 'billing',
        
        // Brand name shown in billing portal
        'brand_name' => 'Billing Portal',
        
        // Primary color for billing portal
        'primary_color' => '#6366f1',
        
        // Enable login page for billing portal
        'login_enabled' => true,
        
        // Auth guard for billing portal
        'auth_guard' => 'web',
        
        // Allowed roles (empty = all authenticated users)
        'allowed_roles' => [],
        
        // Billing portal features
        'features' => [
            'subscriptions' => true,
            'payment_methods' => true,
            'invoices' => true,
        ],
        
        // Invoice display settings
        'invoice' => [
            'vendor_name' => null,
            'product_name' => 'Subscription',
        ],
        
        // Redirect URLs after actions
        'redirects' => [
            'after_payment_method_added' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        // Navigation sort order for resources
        'navigation_sort' => [
            'subscriptions' => 10,
            'customers' => 20,
            'invoices' => 30,
        ],
    ],
];
```

## Navigation Configuration

### Change Navigation Group

```php
'navigation' => [
    'group' => 'Subscriptions', // or null to not group
],
```

### Badge Colors

Available colors: `primary`, `secondary`, `success`, `danger`, `warning`, `info`, `gray`.

```php
'navigation' => [
    'badge_color' => 'primary',
],
```

## Table Configuration

### Polling Interval

Set how often tables auto-refresh:

```php
'tables' => [
    'polling_interval' => '30s',  // 30 seconds
    'polling_interval' => '2m',   // 2 minutes
    'polling_interval' => null,   // Disable polling
],
```

### Date Format

Uses PHP date format:

```php
'tables' => [
    'date_format' => 'd M Y, H:i',    // 15 Jan 2024, 14:30
    'date_format' => 'Y-m-d',         // 2024-01-15
    'date_format' => 'M d, Y g:i A',  // Jan 15, 2024 2:30 PM
],
```

## Feature Toggles

### Disable Specific Resources

```php
'features' => [
    'subscriptions' => true,
    'customers' => false,  // Hide customer resource
    'invoices' => true,
],
```

### Disable Specific Widgets

```php
'features' => [
    'dashboard' => [
        'widgets' => [
            'mrr' => true,
            'active_subscribers' => true,
            'churn_rate' => false,         // Hide this widget
            'attention_required' => true,
            'revenue_chart' => false,      // Hide this widget
            'subscription_distribution' => true,
            'trial_conversions' => true,
        ],
    ],
],
```

## Billing Portal Configuration

### Custom Panel ID

```php
'billing' => [
    'panel_id' => 'customer-billing',
    'path' => 'my-billing',
],
```

Access at: `https://yourapp.com/my-billing`

### Role-Based Access

```php
'billing' => [
    'allowed_roles' => ['customer', 'subscriber'],
],
```

Requires Spatie Permission or similar role middleware.

### Custom Auth Guard

```php
'billing' => [
    'auth_guard' => 'customer',
],
```

### Billable Model

```php
'billing' => [
    // Use Team instead of User as billable
    'billable_model' => \App\Models\Team::class,
],
```

## Environment Variables

Override config via `.env`:

```env
# Disable specific features
FILAMENT_CASHIER_CHIP_CUSTOMERS=false
FILAMENT_CASHIER_CHIP_INVOICES=false

# Billing portal settings
FILAMENT_CASHIER_CHIP_BILLING_PATH=account/billing
```

Use in config:

```php
'features' => [
    'customers' => env('FILAMENT_CASHIER_CHIP_CUSTOMERS', true),
    'invoices' => env('FILAMENT_CASHIER_CHIP_INVOICES', true),
],
```

## Multi-Tenancy Configuration

The package automatically inherits owner scoping from `cashier-chip`:

```php
// config/cashier-chip.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
    ],
],
```

When enabled:
- All resources are owner-scoped
- Queries use `CashierChipOwnerScope::apply()`
- Cross-tenant data is hidden

## Resource Sort Order

Change navigation order:

```php
'resources' => [
    'navigation_sort' => [
        'invoices' => 10,       // First
        'subscriptions' => 20,  // Second
        'customers' => 30,      // Third
    ],
],
```

## Next Steps

- [Resources](04-resources.md) – Admin panel resources
- [Billing Portal](05-billing-portal.md) – Customer self-service
- [Widgets](06-widgets.md) – Dashboard analytics
