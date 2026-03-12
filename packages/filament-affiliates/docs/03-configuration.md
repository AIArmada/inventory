---
title: Configuration
---

# Configuration

The plugin is configured via `config/filament-affiliates.php`.

## Full Configuration

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation_group' => 'E-commerce',

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */

    'widgets' => [
        'currency' => env('AFFILIATES_DEFAULT_CURRENCY', 'USD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'admin' => [
            'conversions' => true,
            'payouts' => true,
            'programs' => true,
            'fraud_monitoring' => true,
            'reports' => true,
            'network_visualization' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Affiliate Portal
    |--------------------------------------------------------------------------
    */

    'portal' => [
        'panel_id' => env('AFFILIATES_PORTAL_PANEL_ID', 'affiliate'),
        'path' => env('AFFILIATES_PORTAL_PATH', 'affiliate'),
        'brand_name' => env('AFFILIATES_PORTAL_BRAND_NAME', 'Affiliate Portal'),
        'primary_color' => env('AFFILIATES_PORTAL_PRIMARY_COLOR', '#6366f1'),
        'login_enabled' => env('AFFILIATES_PORTAL_LOGIN_ENABLED', true),
        'registration_enabled' => env('AFFILIATES_PORTAL_REGISTRATION_ENABLED', true),
        'auth_guard' => env('AFFILIATES_PORTAL_AUTH_GUARD', 'web'),
        'features' => [
            'dashboard' => true,
            'links' => true,
            'conversions' => true,
            'payouts' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */

    'integrations' => [
        'filament_cart' => true,
        'filament_vouchers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    'resources' => [
        'navigation_sort' => [
            'affiliates' => 60,
            'affiliate_conversions' => 61,
            'affiliate_payouts' => 62,
        ],
    ],
];
```

## Configuration Options

### Navigation Group

Set the navigation group for all affiliate resources:

```php
'navigation_group' => 'Sales & Marketing',
```

### Widget Settings

```php
'widgets' => [
    // Currency for monetary displays
    'currency' => 'USD',
],
```

### Portal Settings

| Key | Description | Default |
|-----|-------------|---------|
| `panel_id` | Filament panel ID | `affiliate` |
| `path` | URL path for portal | `affiliate` |
| `brand_name` | Portal brand name | `Affiliate Portal` |
| `primary_color` | Primary color hex | `#6366f1` |
| `login_enabled` | Show login page | `true` |
| `registration_enabled` | Allow self-registration | `true` |
| `auth_guard` | Laravel auth guard | `web` |

### Portal Features

Enable/disable specific portal pages:

```php
'features' => [
    'dashboard' => true,     // Main dashboard
    'links' => true,         // Link generator
    'conversions' => true,   // Conversion history
    'payouts' => true,       // Payout history
],
```

### Integration Settings

Auto-detect and enable integrations:

```php
'integrations' => [
    // Show deep links to cart snapshots
    'filament_cart' => true,

    // Show deep links to vouchers
    'filament_vouchers' => true,
],
```

### Admin Feature Flags

```php
'features' => [
    'admin' => [
        'conversions' => true,
        'payouts' => true,
        'programs' => true,
        'fraud_monitoring' => true,
        'reports' => true,
        'network_visualization' => true,
    ],
],
```

`payouts` and `programs` are force-disabled by the plugin when `affiliates.features.commission_tracking.enabled` is false.

### Resource Navigation Sort

Control the order of resources in navigation:

```php
'resources' => [
    'navigation_sort' => [
        'affiliates' => 60,
        'affiliate_conversions' => 61,
        'affiliate_payouts' => 62,
        'affiliate_programs' => 63,
        'affiliate_fraud_signals' => 64,
    ],
],
```

## Environment Variables

Available environment variables:

```env
# Portal
AFFILIATES_PORTAL_PANEL_ID=affiliate
AFFILIATES_PORTAL_PATH=affiliate
AFFILIATES_PORTAL_BRAND_NAME="Affiliate Portal"
AFFILIATES_PORTAL_PRIMARY_COLOR=#6366f1
AFFILIATES_PORTAL_LOGIN_ENABLED=true
AFFILIATES_PORTAL_REGISTRATION_ENABLED=true
AFFILIATES_PORTAL_AUTH_GUARD=web
```

## Programmatic Registration

Register the plugin in your panel provider:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentAffiliatesPlugin::make(),
    ]);
}
```
