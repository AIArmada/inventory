---
title: Configuration
---

# Configuration

Complete configuration reference for the `filament-cart` package.

## Publishing Configuration

```bash
php artisan vendor:publish --tag="filament-cart-config"
```

Creates `config/filament-cart.php`.

## Full Configuration Reference

```php
<?php

declare(strict_types=1);

use AIArmada\Cart\Services\BuiltInRulesFactory;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Configure database table names and JSON column types.
    |
    */
    'database' => [
        // Prefix for all package tables
        'table_prefix' => 'cart_',
        
        // JSON column type: 'json' (MySQL/SQLite) or 'jsonb' (PostgreSQL)
        'json_column_type' => env('FILAMENT_CART_JSON_COLUMN_TYPE', 
            env('COMMERCE_JSON_COLUMN_TYPE', 'json')
        ),
        
        // Override individual table names
        'tables' => [
            'snapshots' => 'cart_snapshots',
            'snapshot_items' => 'cart_snapshot_items',
            'snapshot_conditions' => 'cart_snapshot_conditions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Configure Filament navigation settings.
    |
    */
    'navigation_group' => 'E-Commerce',

    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
            'cart_items' => 31,
            'cart_conditions' => 32,
            'conditions' => 33,
            'recovery_campaigns' => 40,
            'recovery_templates' => 41,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Configure Filament table behavior.
    |
    */
    'polling_interval' => '30s',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Toggle package features on/off.
    |
    */
    'features' => [
        // Cart dashboard page
        'dashboard' => true,
        
        // Analytics page and widgets
        'analytics' => true,
        
        // Cart recovery system (campaigns, templates, attempts)
        'recovery' => true,
        
        // Real-time monitoring and alerts
        'monitoring' => true,
        
        // Auto-apply global conditions to carts
        'global_conditions' => true,
        
        // Track cart abandonment
        'abandonment_tracking' => true,
        
        // Fraud detection features
        'fraud_detection' => true,
        
        // Collaborative/shared carts
        'collaborative_carts' => true,
        
        // AI-powered recovery optimization
        'ai_recovery' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner Scoping (Multitenancy)
    |--------------------------------------------------------------------------
    |
    | Configure owner-based data isolation.
    |
    */
    'owner' => [
        // Enable owner scoping
        'enabled' => env('FILAMENT_CART_OWNER_ENABLED', false),
        
        // Include global (ownerless) records when scoping
        'include_global' => env('FILAMENT_CART_OWNER_INCLUDE_GLOBAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Rules Factory
    |--------------------------------------------------------------------------
    |
    | Factory class for creating dynamic condition rules.
    |
    */
    'dynamic_rules_factory' => BuiltInRulesFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Dashboard Widgets
    |--------------------------------------------------------------------------
    |
    | Toggle individual dashboard widgets.
    |
    */
    'widgets' => [
        'stats_overview' => true,
        'abandoned_carts' => true,
        'fraud_detection' => true,
        'recovery_optimizer' => true,
        'collaborative_carts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI/Analytics Settings
    |--------------------------------------------------------------------------
    |
    | Configure thresholds and limits for AI features.
    |
    */
    'ai' => [
        // Maximum recovery attempts per abandoned cart
        'max_recovery_attempts' => 3,
        
        // Days to consider for abandonment window
        'abandonment_window_days' => 7,
        
        // Cart value threshold for "high value" designation (in cents)
        'high_value_threshold_cents' => 10000, // $100
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configure fraud detection behavior.
    |
    */
    'fraud' => [
        // Only show high-risk carts in fraud widgets
        'show_high_risk_only' => true,
        
        // Fraud score threshold for alerts (0.0 - 1.0)
        'alert_threshold' => 0.6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    |
    | Configure cart event synchronization behavior.
    |
    */
    'synchronization' => [
        // Use queue for sync operations (recommended for high traffic)
        'queue_sync' => false,
        
        // Queue connection when queue_sync is enabled
        'queue_connection' => 'default',
        
        // Queue name for sync jobs
        'queue_name' => 'cart-sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure real-time monitoring settings.
    |
    */
    'monitoring' => [
        // Enable monitoring features
        'enabled' => true,
        
        // Polling interval for live dashboard (seconds)
        'polling_interval_seconds' => 10,
        
        // Minutes of inactivity before cart is considered abandoned
        'abandonment_detection_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | Configure alert notification settings.
    |
    */
    'alerts' => [
        // Enable alert system
        'enabled' => true,
        
        // Default cooldown between alerts of same type (minutes)
        'default_cooldown_minutes' => 60,
        
        // Available notification channels
        'channels' => [
            'email' => true,
            'slack' => false,
            'webhook' => false,
            'database' => true,
        ],
        
        // Slack webhook URL for Slack notifications
        'slack_webhook_url' => env('CART_SLACK_WEBHOOK'),
    ],
];
```

## Environment Variables

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `FILAMENT_CART_JSON_COLUMN_TYPE` | string | `json` | JSON column type for migrations |
| `COMMERCE_JSON_COLUMN_TYPE` | string | `json` | Fallback JSON column type |
| `FILAMENT_CART_OWNER_ENABLED` | bool | `false` | Enable owner scoping |
| `FILAMENT_CART_OWNER_INCLUDE_GLOBAL` | bool | `false` | Include global records |
| `CART_SLACK_WEBHOOK` | string | `null` | Slack webhook URL for alerts |

## Feature Toggles

### Disabling Features

Disable features you don't need to reduce resource overhead:

```php
'features' => [
    'recovery' => false,      // Disables recovery resources and pages
    'monitoring' => false,    // Disables alert resources and live monitor
    'fraud_detection' => false,
    'collaborative_carts' => false,
    'ai_recovery' => false,
],
```

### Widget Control

Control which widgets appear on dashboards:

```php
'widgets' => [
    'stats_overview' => true,
    'abandoned_carts' => true,
    'fraud_detection' => false,  // Hide fraud widget
    'recovery_optimizer' => true,
    'collaborative_carts' => false,
],
```

## Custom Navigation Group

Change the navigation group for all cart resources:

```php
'navigation_group' => 'Shop Management',
```

Or customize per-resource by extending:

```php
class CustomCartResource extends CartResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Custom Group';
    }
}
```

## Custom Rules Factory

Register a custom rules factory for dynamic conditions:

```php
'dynamic_rules_factory' => \App\Services\CustomRulesFactory::class,
```

Your factory must implement `RulesFactoryInterface`:

```php
<?php

namespace App\Services;

use AIArmada\Cart\Contracts\RulesFactoryInterface;

class CustomRulesFactory implements RulesFactoryInterface
{
    public function createRules(string $key, array $metadata = []): array
    {
        $context = $metadata['context'] ?? [];
        
        return match ($key) {
            'my-custom-rule' => [
                fn (array $payload): bool => $this->evaluateCustomRule($payload, $context),
            ],
            default => [],
        };
    }
    
    public function canCreateRules(string $key): bool
    {
        return in_array($key, ['my-custom-rule']);
    }
    
    public function getAvailableKeys(): array
    {
        return ['my-custom-rule'];
    }
    
    private function evaluateCustomRule(array $payload, array $context): bool
    {
        // Your custom rule logic
        return true;
    }
}
```

## Database Table Customization

### Custom Table Prefix

```php
'database' => [
    'table_prefix' => 'shop_cart_',  // Results in: shop_cart_snapshots, etc.
],
```

### Custom Table Names

Override specific table names:

```php
'database' => [
    'tables' => [
        'snapshots' => 'my_custom_carts',
        'snapshot_items' => 'my_custom_cart_items',
        'snapshot_conditions' => 'my_custom_cart_conditions',
    ],
],
```

### PostgreSQL JSONB

For PostgreSQL, use JSONB for better performance:

```php
'database' => [
    'json_column_type' => 'jsonb',
],
```

Or via environment:

```env
FILAMENT_CART_JSON_COLUMN_TYPE=jsonb
```

## Queue Configuration

For high-traffic applications, enable queued synchronization:

```php
'synchronization' => [
    'queue_sync' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'cart-sync',
],
```

Then run a dedicated worker:

```bash
php artisan queue:work redis --queue=cart-sync
```

## Monitoring Thresholds

Adjust monitoring sensitivity:

```php
'monitoring' => [
    // More sensitive abandonment detection
    'abandonment_detection_minutes' => 15,
    
    // Faster live dashboard updates
    'polling_interval_seconds' => 5,
],

'ai' => [
    // Lower threshold for high-value designation
    'high_value_threshold_cents' => 5000, // $50
],

'fraud' => [
    // More aggressive fraud alerting
    'alert_threshold' => 0.4,
],
```

## Alert Channel Configuration

### Email Alerts

Configure in your alert rules. Emails use Laravel's mail configuration.

### Slack Alerts

Set the webhook URL:

```env
CART_SLACK_WEBHOOK=https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX
```

Enable in config:

```php
'alerts' => [
    'channels' => [
        'slack' => true,
    ],
    'slack_webhook_url' => env('CART_SLACK_WEBHOOK'),
],
```

### Webhook Alerts

Configure webhook URLs per alert rule in the admin interface.

## Polling Intervals

### Table Polling

Refresh tables at specified intervals:

```php
'polling_interval' => '30s',  // 30 seconds
// Or disable: 'polling_interval' => null,
```

### Live Dashboard Polling

For the live monitor:

```php
'monitoring' => [
    'polling_interval_seconds' => 10,
],
```

Widgets can also set their own polling:

```php
class LiveStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';
}
```
